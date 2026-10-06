<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Coordinates cache invalidation when CMS content changes.
 *
 * The app runs as an unprivileged user and cannot delete LiteSpeed's
 * root-owned on-disk cache, so purging is done by emitting an
 * `X-LiteSpeed-Purge` response header (honoured by the OLS cache module).
 * The header is attached by App\Http\Middleware\PurgeLiteSpeedCache.
 */
class LiteSpeedCache
{
    protected static bool $purge = false;

    /** Flag the current response to carry an X-LiteSpeed-Purge header. */
    public static function flagPurge(): void
    {
        static::$purge = true;
    }

    public static function shouldPurge(): bool
    {
        if (static::$purge) {
            return true;
        }

        // Purge requested from a queue job / CLI (no response to attach the
        // header to): the next web response carries it instead.
        $flag = static::flagFile();
        if (is_file($flag) && @unlink($flag)) {
            return true;
        }

        return false;
    }

    /** Request a full-page purge from outside an HTTP request (jobs, artisan). */
    public static function queuePurge(): void
    {
        @touch(static::flagFile());
    }

    protected static function flagFile(): string
    {
        return storage_path('framework/litespeed-purge.flag');
    }

    /**
     * Invalidate everything affected by a CMS content change: the
     * Laravel-side sitemap caches and the LiteSpeed full-page cache.
     */
    public static function bustForContentChange(): void
    {
        foreach (['sitemap.index.xml', 'sitemap.static.xml', 'sitemap.pages.xml', 'sitemap.vehicles.xml'] as $key) {
            Cache::forget($key);
        }

        static::flagPurge();
    }
}
