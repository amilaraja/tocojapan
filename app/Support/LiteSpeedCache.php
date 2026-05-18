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
        return static::$purge;
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
