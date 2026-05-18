<?php

namespace App\Http\Middleware;

use App\Support\LiteSpeedCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PurgeLiteSpeedCache
{
    /**
     * If CMS content changed during this request, tell the LiteSpeed
     * cache module to drop its full-page cache for the whole vhost.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (LiteSpeedCache::shouldPurge()) {
            $response->headers->set('X-LiteSpeed-Purge', '*');
        }

        return $response;
    }
}
