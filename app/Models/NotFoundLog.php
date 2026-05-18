<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class NotFoundLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'hits' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    /** Upsert a 404 hit for the given path, aggregating by path. */
    public static function record(string $path, Request $request): void
    {
        $log = static::firstOrNew(['path' => $path]);
        $log->hits = ($log->hits ?? 0) + 1;
        $log->last_seen_at = now();
        $log->referer = mb_substr((string) $request->headers->get('referer'), 0, 1000) ?: $log->referer;
        $log->user_agent = mb_substr((string) $request->userAgent(), 0, 500);
        $log->ip = $request->ip();
        $log->saveQuietly();
    }
}
