<?php

namespace App\Modules\PartnerApi\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An organisation under contract that may show Toco stock (SRS §4).
 * Listing partners route buyers to Toco; selling partners may take a
 * buyer's payment and must hold the car first (Amendment 01, FR-PRT-04).
 */
class Partner extends Model
{
    public const TYPE_LISTING = 'listing';

    public const TYPE_SELLING = 'selling';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REVOKED = 'revoked';

    /** Settings that change which vehicles, prices or photos a partner gets. */
    public const SCOPE_FIELDS = ['stock_scope', 'price_mode', 'price_markup', 'field_set', 'image_mode',
        'image_max_width', 'image_ref_pill', 'image_delivery'];

    /** Attributes never copied into the audit log in clear. */
    protected const AUDIT_MASKED = ['webhook_secret'];

    protected $guarded = ['id', 'attribution_code'];

    /** Column defaults, mirrored so a new model (and its audit entry) carries them. */
    protected $attributes = [
        'type' => self::TYPE_LISTING,
        'status' => self::STATUS_ACTIVE,
        'scope_version' => 1,
        'field_set' => 'standard',
        'price_mode' => 'as_is',
        'image_mode' => 'watermarked',
        'image_ref_pill' => true,
        'image_delivery' => 'rehost',
    ];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'stock_scope' => 'array',
            'ip_allowlist' => 'array',
            'image_ref_pill' => 'boolean',
            'price_markup' => 'decimal:2',
            'contract_starts_on' => 'date',
            'contract_ends_on' => 'date',
            'webhook_secret' => 'encrypted',
            'revoked_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Partner $partner): void {
            $defaults = config('partner_api.defaults');
            foreach ($defaults as $attribute => $value) {
                $partner->{$attribute} ??= $value;
            }
            $partner->attribution_code ??= self::newAttributionCode();
        });

        // Anything that changes what the partner sees bumps scope_version: list
        // responses carry it, and a new value tells the partner to resync fully.
        static::updating(function (Partner $partner): void {
            if ($partner->isDirty(self::SCOPE_FIELDS)) {
                $partner->scope_version = (int) $partner->getOriginal('scope_version') + 1;
            }
        });

        static::saving(function (Partner $partner): void {
            if ($partner->webhook_url && ! $partner->webhook_secret) {
                $partner->webhook_secret = 'whsec_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            }
        });

        static::created(function (Partner $partner): void {
            PartnerAuditLog::record($partner, 'partner.created', after: $partner->auditable());
        });

        // FR-PRT-03: every settings change is versioned with before and after.
        static::updated(function (Partner $partner): void {
            $changed = array_diff(array_keys($partner->getChanges()), ['updated_at', 'last_activity_at']);
            if ($changed === []) {
                return;
            }

            $before = $after = [];
            foreach ($changed as $attribute) {
                $masked = in_array($attribute, self::AUDIT_MASKED, true);
                $before[$attribute] = $masked ? '(changed)' : $partner->getOriginal($attribute);
                $after[$attribute] = $masked ? '(changed)' : $partner->getAttribute($attribute);
            }

            PartnerAuditLog::record($partner, 'partner.updated', $before, $after);
        });
    }

    /** The signing secret for change notifications, created on first need (§8.5). */
    public function ensureWebhookSecret(): string
    {
        if (! $this->webhook_secret) {
            $this->forceFill(['webhook_secret' => 'whsec_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=')])->save();
        }

        return $this->webhook_secret;
    }

    public function rotateWebhookSecret(): string
    {
        $this->forceFill(['webhook_secret' => null]);

        return $this->ensureWebhookSecret();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(PartnerClient::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PartnerAuditLog::class);
    }

    public function isSelling(): bool
    {
        return $this->type === self::TYPE_SELLING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Why this partner may not use the API today, or null when it may.
     * Codes double as problem types (see docs/guide.md, Errors).
     */
    public function accessDenialCode(): ?string
    {
        if ($this->status === self::STATUS_REVOKED) {
            return 'partner_revoked';
        }
        if ($this->status === self::STATUS_SUSPENDED) {
            return 'partner_suspended';
        }

        $today = CarbonImmutable::now(config('partner_api.timezone'))->toDateString();
        if ($this->contract_starts_on && $this->contract_starts_on->toDateString() > $today) {
            return 'contract_not_started';
        }
        if ($this->contract_ends_on && $this->contract_ends_on->toDateString() < $today) {
            return 'contract_ended';
        }

        return null;
    }

    /** Server IP allow-list (§8.4); empty means any address. */
    public function allowsIp(?string $ip): bool
    {
        $ranges = array_values(array_filter($this->ip_allowlist ?? []));

        return $ranges === [] || ($ip !== null && \Symfony\Component\HttpFoundation\IpUtils::checkIp($ip, $ranges));
    }

    public function suspend(string $reason): void
    {
        $this->forceFill(['status' => self::STATUS_SUSPENDED, 'status_reason' => $reason])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill(['status' => self::STATUS_ACTIVE, 'status_reason' => null])->save();
    }

    /** Permanent: every key stops working and all live tokens are deleted. */
    public function revoke(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'status_reason' => $reason,
            'revoked_at' => now(),
        ])->save();

        $this->clients()->where('status', PartnerClient::STATUS_ACTIVE)->get()
            ->each(fn (PartnerClient $client) => $client->revoke('Partner revoked'));
    }

    /** @return array<string, mixed> */
    public function auditable(): array
    {
        return collect($this->attributesToArray())
            ->except(['id', 'created_at', 'updated_at', 'last_activity_at', ...self::AUDIT_MASKED])
            ->all();
    }

    /** Short, unambiguous code for ?ref= deep links (FR-STK-05). */
    public static function newAttributionCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = 'P-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('attribution_code', $code)->exists());

        return $code;
    }
}
