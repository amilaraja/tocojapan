<?php

namespace App\Models;

use App\Support\LiteSpeedCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if (blank($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });

        $bust = fn () => LiteSpeedCache::bustForContentChange();
        static::saved($bust);
        static::deleted($bust);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')->singleFile();
    }

    /** @return BelongsTo<PostCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /** @param Builder<Post> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function getFeaturedUrl(): ?string
    {
        $media = $this->getFirstMedia('featured');

        return $media?->getUrl();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || $this->published_at->isPast());
    }
}
