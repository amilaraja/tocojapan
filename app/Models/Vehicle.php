<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

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
        'fb_shared_at' => 'datetime',
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

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * @param  Builder<Vehicle>  $query
     */
    public function scopePublished($query): void
    {
        $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter($query, array $filters): void
    {
        $query
            ->when(! empty($filters['make']), fn ($q) => $q->whereHas('make', fn ($q) => $q->where('slug', $filters['make'])))
            ->when(! empty($filters['vehicle_model']), fn ($q) => $q->whereHas('vehicleModel', fn ($q) => $q->where('slug', $filters['vehicle_model'])))
            ->when(! empty($filters['body_type']), fn ($q) => $q->whereHas('bodyType', fn ($q) => $q->where('slug', $filters['body_type'])))
            ->when(! empty($filters['year_from']), fn ($q) => $q->where('year_first_reg', '>=', (int) $filters['year_from']))
            ->when(! empty($filters['year_to']), fn ($q) => $q->where('year_first_reg', '<=', (int) $filters['year_to']))
            ->when(! empty($filters['price_from']), fn ($q) => $q->where('price_fob', '>=', (float) $filters['price_from']))
            ->when(! empty($filters['price_to']), fn ($q) => $q->where('price_fob', '<=', (float) $filters['price_to']))
            ->when(! empty($filters['mileage_max']), fn ($q) => $q->where('mileage_km', '<=', (int) $filters['mileage_max']))
            ->when(! empty($filters['transmission']), fn ($q) => $q->where('transmission', $filters['transmission']))
            ->when(! empty($filters['fuel']), fn ($q) => $q->where('fuel', $filters['fuel']))
            ->when(! empty($filters['steering']), fn ($q) => $q->where('steering_side', $filters['steering']))
            ->when(! empty($filters['drive']), fn ($q) => $q->where('drive', $filters['drive']))
            ->when(! empty($filters['q']), fn ($q) => $q->where(function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where('title', 'like', $term)
                    ->orWhere('ref_no', 'like', $term)
                    ->orWhereHas('make', fn ($q) => $q->where('name', 'like', $term))
                    ->orWhereHas('vehicleModel', fn ($q) => $q->where('name', 'like', $term));
            }));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ref_no', 'status', 'price_fob', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
        $this->addMediaCollection('documents')->singleFile();
    }
}
