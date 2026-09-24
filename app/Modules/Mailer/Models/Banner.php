<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $table = 'mailer_banners';

    protected $guarded = [];

    protected $casts = ['archived_at' => 'datetime'];

    /** @param  Builder<Banner>  $query */
    public function scopeActive($query): void
    {
        $query->whereNull('archived_at');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
