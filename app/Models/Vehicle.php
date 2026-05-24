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
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Vehicle extends Model implements HasMedia
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $guarded = [];

    /**
     * Stamp `published_at` automatically when an admin flips status to
     * 'published' without supplying one. Without this, freshly-published
     * vehicles would sort to the bottom of the listing (ORDER BY
     * published_at DESC, MySQL puts NULL last).
     */
    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if ($vehicle->status === 'published' && $vehicle->published_at === null) {
                $vehicle->published_at = now();
            }
        });
    }

    protected $casts = [
        'features' => 'array',
        'seo' => 'array',
        'price_fob' => 'decimal:2',
        'price_fob_discount' => 'decimal:2',
        'm3' => 'decimal:4',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'price_on_request' => 'bool',
        'is_featured' => 'bool',
        'published_at' => 'datetime',
        'sold_at' => 'datetime',
        'fb_shared_at' => 'datetime',
        'year_first_reg' => 'integer',
        'registration_month' => 'integer',
        'manufacture_year' => 'integer',
        'manufacture_month' => 'integer',
        'mileage_km' => 'integer',
        'engine_cc' => 'integer',
    ];

    /** Registration YYYY/MM string for display. Returns just the year when month is unknown. */
    public function registrationYmDisplay(): ?string
    {
        if (! $this->year_first_reg) {
            return null;
        }

        return $this->registration_month
            ? sprintf('%04d/%02d', $this->year_first_reg, $this->registration_month)
            : (string) $this->year_first_reg;
    }

    /** Manufacture YYYY/MM string for display. */
    public function manufactureYmDisplay(): ?string
    {
        if (! $this->manufacture_year) {
            return null;
        }

        return $this->manufacture_month
            ? sprintf('%04d/%02d', $this->manufacture_year, $this->manufacture_month)
            : (string) $this->manufacture_year;
    }

    /**
     * Public-facing chassis number with the bulk of the digits masked.
     * Pattern: keep the first 4 characters, mask the rest with asterisks
     * preserving any dashes. Returns null when no chassis is recorded.
     */
    public function chassisNumberRedacted(): ?string
    {
        $raw = (string) ($this->chassis_number ?? '');
        if ($raw === '') {
            return null;
        }
        $keep = 4;
        $head = mb_substr($raw, 0, $keep);
        $tail = mb_substr($raw, $keep);
        $masked = preg_replace_callback('/[A-Za-z0-9]/u', fn () => '*', $tail) ?? $tail;

        return $head.$masked;
    }

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
        // Visible if status='published' OR status='sold' within the last 90 days.
        // Sold vehicles auto-hide after 3 months without a manual archive step.
        //
        // `published_at` is intentionally NOT a visibility gate — clicking
        // publish makes the vehicle live globally and immediately. The column
        // is used only for sort order (so admins can re-bump a listing by
        // bumping its timestamp). A future-dated value used to silently hide
        // the row, which surprised us with stock E02020.
        $query->where(function ($q) {
            $q->where('status', 'published')
                ->orWhere(function ($qq) {
                    $qq->where('status', 'sold')
                        ->where('sold_at', '>=', now()->subDays(90));
                });
        });
    }

    public function isSold(): bool
    {
        return $this->status === 'sold';
    }

    /**
     * Related vehicles for the detail page. Ranks by a fixed priority:
     *
     *   tier 1 — same make AND same model       (highest)
     *   tier 2 — same make AND same body type
     *   tier 3 — same make
     *   tier 4 — same body type
     *   then  — nearest effective price, then nearest year
     *
     * One query, no N+1; sold-within-90-days are included (the public scope
     * keeps them visible for that window) but the current vehicle itself is
     * excluded.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    public function relatedVehicles(int $limit = 8): \Illuminate\Database\Eloquent\Collection
    {
        $currentPrice = (float) ($this->effectivePriceFob() ?? $this->price_fob ?? 0);
        $currentYear = (int) ($this->year_first_reg ?? 0);

        return static::query()
            ->published()
            ->where('id', '<>', $this->id)
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByRaw('CASE WHEN make_id = ? AND vehicle_model_id = ? THEN 1 ELSE 0 END DESC', [$this->make_id, $this->vehicle_model_id])
            ->orderByRaw('CASE WHEN make_id = ? AND body_type_id = ? THEN 1 ELSE 0 END DESC', [$this->make_id, $this->body_type_id])
            ->orderByRaw('CASE WHEN make_id = ? THEN 1 ELSE 0 END DESC', [$this->make_id])
            ->orderByRaw('CASE WHEN body_type_id = ? THEN 1 ELSE 0 END DESC', [$this->body_type_id])
            ->orderByRaw('ABS(COALESCE(price_fob_discount, price_fob, 0) - ?) ASC', [$currentPrice])
            ->orderByRaw('ABS(COALESCE(year_first_reg, 0) - ?) ASC', [$currentYear])
            ->limit($limit)
            ->get();
    }

    /**
     * Price the customer actually pays. Falls back to the listed FOB price
     * when no discount is set. Returns null when the vehicle is "on request".
     */
    public function effectivePriceFob(): ?float
    {
        if ($this->price_on_request) {
            return null;
        }
        if ($this->price_fob_discount !== null && (float) $this->price_fob_discount > 0) {
            return (float) $this->price_fob_discount;
        }

        return $this->price_fob !== null ? (float) $this->price_fob : null;
    }

    public function isDiscounted(): bool
    {
        return ! $this->price_on_request
            && $this->price_fob_discount !== null
            && (float) $this->price_fob_discount > 0
            && (float) $this->price_fob > 0
            && (float) $this->price_fob_discount < (float) $this->price_fob;
    }

    /** @param  Builder<Vehicle>  $query */
    public function scopeFeatured($query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * Strip empty/false/null entries from the features JSON before persisting.
     * Filament's Toggle dehydrate returns null when off; cleaning here means
     * the stored shape stays `{group: {key: 'Label'}}` without no-op nulls.
     *
     * @param  array<string, array<string, mixed>>|null  $value
     */
    public function setFeaturesAttribute($value): void
    {
        if (! is_array($value)) {
            $this->attributes['features'] = $value;

            return;
        }
        $cleaned = [];
        foreach ($value as $group => $items) {
            if (! is_array($items)) {
                continue;
            }
            $kept = array_filter($items, fn ($v) => $v !== null && $v !== false && $v !== '');
            if ($kept) {
                $cleaned[$group] = $kept;
            }
        }
        $this->attributes['features'] = $cleaned ? json_encode($cleaned, JSON_UNESCAPED_UNICODE) : null;
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
            // Price filters compare against the *effective* price (discount when
            // set, otherwise the listed FOB). COALESCE handles NULL discounts
            // without excluding the row.
            ->when(! empty($filters['price_from']), fn ($q) => $q->whereRaw('COALESCE(price_fob_discount, price_fob) >= ?', [(float) $filters['price_from']]))
            ->when(! empty($filters['price_to']), fn ($q) => $q->whereRaw('COALESCE(price_fob_discount, price_fob) <= ?', [(float) $filters['price_to']]))
            ->when(! empty($filters['mileage_max']), fn ($q) => $q->where('mileage_km', '<=', (int) $filters['mileage_max']))
            ->when(! empty($filters['transmission']), fn ($q) => $q->where('transmission', $filters['transmission']))
            ->when(! empty($filters['fuel']), fn ($q) => $q->where('fuel', $filters['fuel']))
            ->when(! empty($filters['steering']), fn ($q) => $q->where('steering_side', $filters['steering']))
            ->when(! empty($filters['drive']), fn ($q) => $q->where('drive', $filters['drive']))
            ->when(! empty($filters['featured']), fn ($q) => $q->where('is_featured', true))
            ->when(! empty($filters['q']), fn ($q) => $q->where(function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where('title', 'like', $term)
                    ->orWhere('ref_no', 'like', $term)
                    ->orWhere('stock_no', 'like', $term)
                    ->orWhereHas('make', fn ($q) => $q->where('name', 'like', $term))
                    ->orWhereHas('vehicleModel', fn ($q) => $q->where('name', 'like', $term));
            }));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ref_no', 'status', 'price_fob', 'price_fob_discount', 'is_featured', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
        $this->addMediaCollection('documents')->singleFile();
        $this->addMediaCollection('video')
            ->singleFile()
            ->acceptsMimeTypes(['video/mp4', 'video/webm', 'video/quicktime']);
    }

    /** Public URL of the vehicle's walkaround video, or null when none. */
    public function videoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('video');

        return $url ?: null;
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Tiny WebP for the detail-page thumbnail strip.
        $this->addMediaConversion('thumb')
            ->performOnCollections('photos')
            ->fit(Fit::Crop, 300, 225)
            ->format('webp')
            ->quality(70)
            ->nonQueued();

        // Small WebP for listing cards — keeps the homepage/listing payload
        // tiny instead of shipping the full photo.
        $this->addMediaConversion('card')
            ->performOnCollections('photos')
            ->fit(Fit::Crop, 560, 420)
            ->format('webp')
            ->quality(72)
            ->nonQueued();

        // 1280px WebP for the detail-page hero + retina (2x) card srcset.
        $this->addMediaConversion('gallery')
            ->performOnCollections('photos')
            ->width(1280)
            ->format('webp')
            ->quality(80)
            ->nonQueued();
    }

    /** Card-sized photo URL — uses the 'card' conversion once it is generated. */
    public function cardPhotoUrl(): ?string
    {
        return $this->conversionUrl('card');
    }

    /** 1280px hero/retina photo URL — uses the 'gallery' conversion. */
    public function galleryPhotoUrl(): ?string
    {
        return $this->conversionUrl('gallery');
    }

    /** First-photo URL for a given conversion, falling back to the original. */
    protected function conversionUrl(string $conversion): ?string
    {
        $media = $this->getFirstMedia('photos');

        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }
}
