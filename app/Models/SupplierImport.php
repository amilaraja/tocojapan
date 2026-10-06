<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One uploaded supplier stock file and its preview/apply progress. */
class SupplierImport extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_STAGING = 'staging';

    public const STATUS_PREVIEWING = 'previewing';

    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_APPLYING = 'applying';

    public const STATUS_DELISTING = 'delisting';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_QUEUED => 'Waiting',
        self::STATUS_STAGING => 'Reading file',
        self::STATUS_PREVIEWING => 'Comparing',
        self::STATUS_PREVIEWED => 'Ready to review',
        self::STATUS_APPLYING => 'Applying',
        self::STATUS_DELISTING => 'Delisting',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $guarded = [];

    protected $casts = [
        'stats' => 'array',
        'errors_sample' => 'array',
        'started_at' => 'datetime',
        'approved_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SupplierImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(SupplierImportRow::class);
    }

    public function stat(string $key, mixed $default = 0): mixed
    {
        return ($this->stats ?? [])[$key] ?? $default;
    }

    /** @param  array<string, mixed>  $values */
    public function mergeStats(array $values): void
    {
        $this->stats = array_merge($this->stats ?? [], $values);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [
            self::STATUS_QUEUED, self::STATUS_STAGING, self::STATUS_PREVIEWING,
            self::STATUS_APPLYING, self::STATUS_DELISTING,
        ], true);
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === self::STATUS_PREVIEWED;
    }

    /** True when the preview would delist more live stock than the supplier's guard allows. */
    public function needsDelistConfirmation(): bool
    {
        return (bool) $this->stat('delist_guard_tripped', false);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }
}
