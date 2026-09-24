<?php

namespace App\Modules\Mailer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    // TOC-CB-006
    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_BREVO = 'in_brevo';

    public const STATUS_CHANGED = 'changed';

    public const STATUS_SENT = 'sent';

    public const STATUS_ARCHIVED = 'archived';

    /** @var array<string, string> Plain-English labels (TOC-GEN-007). */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_IN_BREVO => 'In Brevo (draft)',
        self::STATUS_CHANGED => 'Changed since push',
        self::STATUS_SENT => 'Sent',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    /** @var array<string, string> Filament colour per status. */
    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_IN_BREVO => 'info',
        self::STATUS_CHANGED => 'warning',
        self::STATUS_SENT => 'success',
        self::STATUS_ARCHIVED => 'gray',
    ];

    protected $table = 'mailer_campaigns';

    protected $guarded = [];

    protected $casts = [
        'list_ids' => 'array',
        'stats' => 'array',
        'pushed_at' => 'datetime',
        'stats_synced_at' => 'datetime',
        'sent_at' => 'datetime',
        'sender_id' => 'integer',
        'brevo_campaign_id' => 'integer',
    ];

    protected static function booted(): void
    {
        // utm_campaign slug (TOC-CMP-005): name + date, unique.
        static::creating(function (Campaign $campaign): void {
            if (filled($campaign->slug)) {
                return;
            }
            $base = Str::limit(Str::slug((string) $campaign->name) ?: 'campaign', 140, '').'-'.now((string) config('mailer.display_timezone'))->format('Y-m-d');
            $slug = $base;
            for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            $campaign->slug = $slug;
        });
    }

    /** @return HasMany<CampaignVehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(CampaignVehicle::class)->orderBy('position');
    }

    /** @return BelongsTo<Banner, $this> */
    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }
}
