<?php

namespace App\Http\Middleware;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a 404 GET response: follow a managed redirect if one exists for the
 * path, otherwise log the miss so admins can review and create redirects.
 */
class HandleNotFound
{
    /** Path prefixes that are never logged (framework / asset noise). */
    protected array $ignore = [
        'livewire', '_debugbar', 'build', 'storage', 'admin', 'api',
        'favicon.ico', 'robots.txt', '.well-known',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! $request->isMethodSafe()) {
            return $response;
        }

        $path = Redirect::normalizePath($request->path());

        // 1. A managed redirect takes the visitor to the right place.
        $redirect = Redirect::query()
            ->where('from_path', $path)
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            $redirect->recordHit();

            return redirect()->to($redirect->target(), $redirect->status_code);
        }

        // 2. Otherwise log the miss (aggregated by path) for review.
        foreach ($this->ignore as $prefix) {
            if ($path === $prefix || str_starts_with($path.'/', $prefix.'/')) {
                return $response;
            }
        }

        try {
            NotFoundLog::record($path === '' ? '/' : $path, $request);
        } catch (\Throwable $e) {
            // Never let logging break the 404 response.
        }

        return $response;
    }
}
