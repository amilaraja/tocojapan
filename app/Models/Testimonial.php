<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Testimonial extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'stars' => 'integer',
        'is_featured' => 'bool',
        'is_published' => 'bool',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true)->where('is_published', true);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // 600px WebP — the display image for the customer-reviews page.
        $this->addMediaConversion('display')
            ->width(600)
            ->format('webp')
            ->nonQueued();

        // 300px WebP — compact thumbnail for the homepage grid.
        $this->addMediaConversion('thumb')
            ->width(300)
            ->format('webp')
            ->nonQueued();
    }

    public function getPhotoUrl(string $conversion = ''): ?string
    {
        $url = $this->getFirstMediaUrl('photo', $conversion);

        return $url ?: null;
    }
}
