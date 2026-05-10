<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quote extends Model
{
    use LogsActivity, SoftDeletes;

    public const STATUSES = [
        'submitted' => 'Submitted',
        'in_progress' => 'In progress',
        'quoted' => 'Quoted',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'archived' => 'Archived',
    ];

    protected $guarded = [];

    protected $casts = [
        'price_quoted' => 'decimal:2',
        'cif_total' => 'decimal:2',
        'valid_until' => 'date',
        'last_admin_reply_at' => 'datetime',
        'last_customer_reply_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quote $q) {
            if (empty($q->reference)) {
                $q->reference = 'Q-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Port, $this> */
    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }

    /** @return HasMany<QuoteMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(QuoteMessage::class)->orderBy('created_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'price_quoted', 'cif_total', 'valid_until'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
