<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'features' => 'array',
        'seo' => 'array',
        'price_fob' => 'decimal:2',
        'm3' => 'decimal:4',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'price_on_request' => 'bool',
        'published_at' => 'datetime',
        'year_first_reg' => 'integer',
        'mileage_km' => 'integer',
        'engine_cc' => 'integer',
    ];

    /** @return BelongsTo<Make, $this> */
    public function make(): BelongsTo
    {
        return $this->belongsTo(Make::class);
    }

    /** @return BelongsTo<VehicleModel, $this> */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    /** @return BelongsTo<BodyType, $this> */
    public function bodyType(): BelongsTo
    {
        return $this->belongsTo(BodyType::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ref_no', 'status', 'price_fob', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
