<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Redirect extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status_code' => 'integer',
        'is_active' => 'bool',
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    /** Record that this redirect was followed. */
    public function recordHit(): void
    {
        $this->forceFill([
            'hits' => $this->hits + 1,
            'last_hit_at' => now(),
        ])->saveQuietly();
    }

    /** Absolute or root-relative target, ready to hand to redirect(). */
    public function target(): string
    {
        if (Str::startsWith($this->to_path, ['http://', 'https://'])) {
            return $this->to_path;
        }

        return '/'.ltrim($this->to_path, '/');
    }

    /** Normalise a path the same way the middleware does (no slashes, no query). */
    public static function normalizePath(string $path): string
    {
        return trim(strtok($path, '?'), '/');
    }
}
