<?php

namespace App\Models;

use App\Support\LiteSpeedCache;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $bust = fn () => LiteSpeedCache::bustForContentChange();

        static::saved($bust);
        static::deleted($bust);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || $this->published_at->isPast());
    }
}
