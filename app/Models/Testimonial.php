<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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

    public function getPhotoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('photo');

        return $url ?: null;
    }
}
