<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
        // Anything created by hand (admin form, factories, API) is own stock
        // unless a supplier is given; feed imports always set it explicitly.
        static::creating(function (Vehicle $vehicle): void {
            if ($vehicle->supplier_id === null && ($own = Supplier::ownStockId())) {
                $vehicle->supplier_id = $own;
            }
        });

        static::saving(function (Vehicle $vehicle): void {
            // Two cars with the same title (or a trashed one) would otherwise
            // collide on the unique slug and crash the admin save.
            if ($vehicle->isDirty('slug') || ! $vehicle->exists) {
                $vehicle->slug = static::uniqueSlug((string) ($vehicle->slug ?: $vehicle->title), $vehicle->id, $vehicle->stock_no);
            }
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
        'delisted_at' => 'datetime',
        'supplier_synced_at' => 'datetime',
        'external_photos' => 'array',
        'supplier_meta' => 'array',
        'sync_locked' => 'bool',
        'source_price' => 'decimal:2',
        'year_first_reg' => 'integer',
        'registration_month' => 'integer',
        'manufacture_year' => 'integer',
        'manufacture_month' => 'integer',
        'mileage_km' => 'integer',
        'engine_cc' => 'integer',
    ];

    /**
     * The slug, or the slug + stock no. / counter when another vehicle
     * (trashed ones included) already uses it.
     */
    public static function uniqueSlug(string $slug, ?int $ignoreId = null, ?string $stockNo = null): string
    {
        $base = Str::slug($slug) ?: 'vehicle';
        $taken = fn (string $candidate) => static::withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if (! $taken($base)) {
            return $base;
        }
        $candidate = $stockNo ? $base.'-'.Str::slug($stockNo) : $base.'-2';
        for ($i = 2; $taken($candidate); $i++) {
            $candidate = $base.'-'.$i;
        }

        return $candidate;
    }

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

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Stock that comes from a supplier feed (OnePrice, JWT …) rather than Toco's yard. */
    public function isSupplierStock(): bool
    {
        return $this->supplier_id !== null && (int) $this->supplier_id !== Supplier::ownStockId();
    }

    /**
     * PayPal / bank-transfer checkout. Supplier stock is quote-only unless the
     * supplier's "allow online checkout" setting is on, because its
     * availability is only confirmed when sales contacts the supplier.
     */
    public function canCheckoutOnline(): bool
    {
        if (! $this->isSupplierStock()) {
            return true;
        }

        return (bool) $this->supplier?->setting('allow_online_checkout');
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

    /**
     * Published vehicles per make_id / body_type_id from one cached GROUP BY
     * (10 min). Replaces correlated COUNT subqueries per make/body type,
     * which get slow once supplier feeds add tens of thousands of rows.
     *
     * @return array<int, int>
     */
    public static function publishedCountsBy(string $column): array
    {
        abort_unless(in_array($column, ['make_id', 'body_type_id'], true), 500);

        return Cache::remember("vehicles.published_counts.{$column}", now()->addMinutes(10), fn () => static::query()
            ->where('status', 'published')
            ->whereNotNull($column)
            ->groupBy($column)
            ->selectRaw("{$column} as k, COUNT(*) as c")
            ->pluck('c', 'k')
            ->map(fn ($c) => (int) $c)
            ->all());
    }

    /**
     * Set `published_count` on each make/body type from publishedCountsBy().
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TModel>  $items
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public static function withPublishedCounts(\Illuminate\Database\Eloquent\Collection $items, string $column): \Illuminate\Database\Eloquent\Collection
    {
        $counts = static::publishedCountsBy($column);

        return $items->each(fn ($item) => $item->setAttribute('published_count', $counts[$item->getKey()] ?? 0));
    }

    public function isSold(): bool
    {
        return $this->status === 'sold';
    }

    /**
     * IDs of the 7 most-recently-published vehicles — the badge population.
     * Cached per request (static) so a card grid only fires one query no
     * matter how many cards render.
     *
     * @return array<int, int>
     */
    public static function latestArrivalIds(int $limit = 7): array
    {
        // Keyed by app instance: one cache per request (and per test), never
        // shared across requests in a long-lived process.
        static $cache = [];
        $key = spl_object_id(app()).':'.$limit;

        if (! isset($cache[$key])) {
            // Badge population is own stock only — a supplier feed import would
            // otherwise flood the "new arrival" slots.
            $cache[$key] = static::query()
                ->where('status', 'published')
                ->where(fn ($q) => $q->where('supplier_id', Supplier::ownStockId())->orWhereNull('supplier_id'))
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck('id')
                ->all();
        }

        return $cache[$key];
    }

    /** Whether this vehicle is among the latest-N new arrivals. */
    public function isNewArrival(int $limit = 7): bool
    {
        return $this->status === 'published'
            && in_array($this->id, static::latestArrivalIds($limit), true);
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
            // Candidate set limited to the two tiers that can rank at all —
            // keeps the sort small now that supplier feeds add tens of
            // thousands of rows.
            ->where(fn ($q) => $q->where('make_id', $this->make_id)
                ->when($this->body_type_id, fn ($q) => $q->orWhere('body_type_id', $this->body_type_id)))
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            // Same source first: own-stock pages recommend own stock.
            ->orderByRaw('CASE WHEN supplier_id = ? THEN 1 ELSE 0 END DESC', [$this->supplier_id])
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

    /**
     * Order by supplier priority (own stock first), as set on each supplier.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeOrderBySupplierPriority($query): void
    {
        $priorities = Supplier::query()->pluck('sort_priority', 'id');
        if ($priorities->unique()->count() <= 1) {
            return;
        }
        $case = 'CASE supplier_id';
        foreach ($priorities as $id => $priority) {
            $case .= ' WHEN '.(int) $id.' THEN '.(int) $priority;
        }
        $query->orderByRaw($case.' ELSE 1000 END ASC');
    }

    /**
     * Own stock plus suppliers whose setting allows this placement
     * ('show_on_homepage', 'in_sitemap').
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeVisibleIn($query, string $placement): void
    {
        $query->where(fn ($q) => $q->whereIn('supplier_id', Supplier::idsWithSetting($placement))->orWhereNull('supplier_id'));
    }

    /**
     * Supplier-feed stock (OnePrice, JWT …), i.e. everything that is not Toco's own.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopePartnerStock($query): void
    {
        $query->whereNotNull('supplier_id')->where('supplier_id', '!=', Supplier::ownStockId());
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
            // supplier=partners → every supplier except Toco's own stock.
            ->when(($filters['supplier'] ?? null) === 'partners', fn ($q) => $q->partnerStock())
            ->when(! empty($filters['supplier']) && $filters['supplier'] !== 'partners', fn ($q) => $q->whereIn('supplier_id', Supplier::query()->where('slug', $filters['supplier'])->select('id')))
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
            ->when(! empty($filters['mileage_min']), fn ($q) => $q->where('mileage_km', '>=', (int) $filters['mileage_min']))
            ->when(! empty($filters['mileage_max']), fn ($q) => $q->where('mileage_km', '<=', (int) $filters['mileage_max']))
            ->when(! empty($filters['engine_from']), fn ($q) => $q->where('engine_cc', '>=', (int) $filters['engine_from']))
            ->when(! empty($filters['engine_to']), fn ($q) => $q->where('engine_cc', '<=', (int) $filters['engine_to']))
            ->when(! empty($filters['transmission']), fn ($q) => $q->where('transmission', $filters['transmission']))
            ->when(! empty($filters['fuel']), fn ($q) => $q->where('fuel', $filters['fuel']))
            ->when(! empty($filters['steering']), fn ($q) => $q->where('steering_side', $filters['steering']))
            ->when(! empty($filters['drive']), fn ($q) => $q->where('drive', $filters['drive']))
            ->when(! empty($filters['featured']), fn ($q) => $q->where('is_featured', true))
            ->when(! empty($filters['discounted']), fn ($q) => $q->whereNotNull('price_fob_discount')->where('price_fob_discount', '>', 0))
            ->when(! empty($filters['new_only']), fn ($q) => $q->where('published_at', '>=', now()->subDays(14)))
            ->when(! empty($filters['q']), fn ($q) => $q->where(function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where('title', 'like', $term)
                    ->orWhere('ref_no', 'like', $term)
                    ->orWhere('stock_no', 'like', $term)
                    ->orWhere('supplier_ref', 'like', $term)
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
        return $this->conversionUrl('card') ?? $this->externalPhotoUrls()[0] ?? null;
    }

    /** 1280px hero/retina photo URL — uses the 'gallery' conversion. Null for hotlinked supplier photos (no 2x variant). */
    public function galleryPhotoUrl(): ?string
    {
        return $this->conversionUrl('gallery');
    }

    /** First photo at original size: uploaded photo, else the supplier's first photo. */
    public function primaryPhotoUrl(): ?string
    {
        return ($this->getFirstMediaUrl('photos') ?: null) ?? $this->externalPhotoUrls()[0] ?? null;
    }

    /**
     * Supplier-hosted photo URLs (hotlinked). Uploaded photos always win:
     * once an admin uploads photos for a supplier vehicle these are ignored.
     *
     * @return array<int, string>
     */
    public function externalPhotoUrls(): array
    {
        return array_values(array_filter((array) ($this->external_photos ?? []), 'is_string'));
    }

    /**
     * Every photo for the detail page in three sizes.
     *
     * @return array{gallery: Collection<int, string>, thumb: Collection<int, string>, full: Collection<int, string>}
     */
    public function photoSet(): array
    {
        $photos = $this->getMedia('photos');
        if ($photos->isNotEmpty()) {
            return [
                'gallery' => $photos->map(fn ($m) => $m->hasGeneratedConversion('gallery') ? $m->getUrl('gallery') : $m->getUrl())->values(),
                'thumb' => $photos->map(fn ($m) => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl())->values(),
                'full' => $photos->map(fn ($m) => $m->getUrl())->values(),
            ];
        }
        $external = collect($this->externalPhotoUrls());

        return ['gallery' => $external, 'thumb' => $external, 'full' => $external];
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
