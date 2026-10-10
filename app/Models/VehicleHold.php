<?php

namespace App\Models;

use App\Modules\PartnerApi\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A claim on one car by one sales channel (SRS-TOC-PAPI section 7). Rows
 * are never deleted: the table plus the activity log is the hold history
 * (FR-HLD-08). Change holds only through App\Services\Holds\HoldService.
 */
class VehicleHold extends Model
{
    use LogsActivity;

    public const CHANNEL_WEB_PAYPAL = 'web_paypal';

    public const CHANNEL_WEB_TT = 'web_tt';

    public const CHANNEL_PROFORMA = 'proforma';

    public const CHANNEL_ADMIN = 'admin';

    public const CHANNEL_PARTNER = 'partner';

    /** Channels a website buyer claims through; one buyer may move between them. */
    public const WEB_CHANNELS = [self::CHANNEL_WEB_PAYPAL, self::CHANNEL_WEB_TT, self::CHANNEL_PROFORMA];

    public const STATE_ACTIVE = 'active';

    public const STATE_RELEASED = 'released';

    public const STATE_EXPIRED = 'expired';

    public const STATE_CONVERTED = 'converted';

    public const CHANNEL_LABELS = [
        self::CHANNEL_WEB_PAYPAL => 'tocojapan.com (PayPal)',
        self::CHANNEL_WEB_TT => 'tocojapan.com (bank transfer)',
        self::CHANNEL_PROFORMA => 'tocojapan.com (proforma invoice)',
        self::CHANNEL_ADMIN => 'Admin',
        self::CHANNEL_PARTNER => 'Partner',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
            'expiry_warned_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function referralPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'referral_partner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /** @param  Builder<VehicleHold>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('state', self::STATE_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->state === self::STATE_ACTIVE;
    }

    public function isWeb(): bool
    {
        return in_array($this->channel, self::WEB_CHANNELS, true);
    }

    /** Who holds the car, in plain words for staff screens. */
    public function holderLabel(): string
    {
        return match (true) {
            $this->channel === self::CHANNEL_PARTNER => $this->partner?->name ?? 'Partner',
            $this->channel === self::CHANNEL_ADMIN && $this->referralPartner !== null => 'Admin, for '.$this->referralPartner->name,
            default => self::CHANNEL_LABELS[$this->channel] ?? $this->channel,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['vehicle_id', 'channel', 'partner_id', 'referral_partner_id', 'buyer_ref', 'state', 'expires_at', 'ended_reason', 'order_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('holds');
    }
}
