<?php

namespace App\Modules\PartnerApi\Models;

use App\Modules\PartnerApi\Domain\Auth\ClientSecret;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One set of connection keys for a partner, e.g. production and staging
 * (SRS §4). The secret is shown once and stored only as a hash (FR-PRT-02).
 */
class PartnerClient extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const ENV_LIVE = 'live';

    public const ENV_TEST = 'test';

    protected $guarded = ['id'];

    protected $hidden = ['secret_hash', 'previous_secret_hash'];

    protected function casts(): array
    {
        return [
            'previous_secret_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(PartnerAccessToken::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Create a client and return it with its plain secret, which is never
     * stored and cannot be shown again.
     *
     * @return array{0: PartnerClient, 1: string}
     */
    public static function issue(Partner $partner, string $label, string $environment = self::ENV_LIVE): array
    {
        $secret = ClientSecret::generate($environment);

        $client = $partner->clients()->create([
            'label' => $label,
            'environment' => $environment,
            'client_id' => ClientSecret::generateClientId(),
            'key_prefix' => ClientSecret::prefix($secret),
            'secret_hash' => ClientSecret::hash($secret),
        ]);

        PartnerAuditLog::record($partner, 'client.created', after: [
            'label' => $label, 'environment' => $environment, 'key_prefix' => $client->key_prefix,
        ], client: $client);

        return [$client, $secret];
    }

    /**
     * New secret; the old one keeps working for the overlap period so the
     * partner can switch over without downtime (FR-PRT-02).
     */
    public function rotate(): string
    {
        $secret = ClientSecret::generate($this->environment);
        $oldPrefix = $this->key_prefix;

        $this->forceFill([
            'previous_secret_hash' => $this->secret_hash,
            'previous_secret_expires_at' => now()->addDays(config('partner_api.auth.secret_overlap_days')),
            'secret_hash' => ClientSecret::hash($secret),
            'key_prefix' => ClientSecret::prefix($secret),
        ])->save();

        PartnerAuditLog::record($this->partner, 'client.rotated',
            ['key_prefix' => $oldPrefix], ['key_prefix' => $this->key_prefix], client: $this);

        return $secret;
    }

    /** Immediate: the secret stops working and live tokens are deleted. */
    public function revoke(?string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
            'previous_secret_hash' => null,
            'previous_secret_expires_at' => null,
        ])->save();

        $this->accessTokens()->delete();

        PartnerAuditLog::record($this->partner, 'client.revoked', after: ['key_prefix' => $this->key_prefix],
            reason: $reason, client: $this);
    }

    /** Current secret, or the previous one inside its overlap window. */
    public function verifySecret(string $secret): bool
    {
        if (ClientSecret::verify($secret, $this->secret_hash)) {
            return true;
        }

        return $this->previous_secret_hash !== null
            && $this->previous_secret_expires_at?->isFuture()
            && ClientSecret::verify($secret, $this->previous_secret_hash);
    }
}
