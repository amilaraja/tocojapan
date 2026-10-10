<?php

namespace App\Modules\PartnerApi\Http\Middleware;

use App\Modules\PartnerApi\Models\PartnerClient;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use App\Modules\PartnerApi\Support\ETag;
use App\Modules\PartnerApi\Support\PartnerApi;
use App\Modules\PartnerApi\Support\PartnerApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * First middleware on every partner route: feature switch, request id,
 * body size cap (§8.4) and the shared response headers.
 */
class PartnerBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        PartnerApi::requestId($request);
        $request->attributes->set('partner_api.started', microtime(true));

        if (! config('partner_api.enabled')) {
            throw new PartnerApiException(404, 'not_found', 'Not found.');
        }

        $max = (int) config('partner_api.request.max_body_bytes');
        if ((int) $request->header('Content-Length', '0') > $max || strlen($request->getContent()) > $max) {
            throw new PartnerApiException(413, 'body_too_large', 'Request body too large.',
                'Request bodies are limited to '.intdiv($max, 1024).' KB.');
        }

        return PartnerApi::finalize($request, $next($request));
    }

    /**
     * After the response is sent: log the call (SRS §8.6) for any request a
     * partner client was identified on. Never the URL, token or secret.
     */
    public function terminate(Request $request, Response $response): void
    {
        $client = $request->attributes->get(PartnerApi::CLIENT);
        if (! $client instanceof PartnerClient) {
            return;
        }

        try {
            $content = $response->getContent();
            PartnerRequestLog::create([
                'partner_id' => $client->partner_id,
                'partner_client_id' => $client->id,
                'request_id' => PartnerApi::requestId($request),
                'kind' => $request->attributes->get(PartnerApi::KIND, PartnerRequestLog::KIND_API),
                'method' => $request->getMethod(),
                'endpoint' => (string) ($request->route()?->getName() ?? 'unknown'),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - (float) $request->attributes->get('partner_api.started', microtime(true))) * 1000),
                'bytes' => $response instanceof BinaryFileResponse ? (int) $response->getFile()->getSize()
                    : ($content !== false ? strlen($content) : 0),
                'counted' => ! $request->attributes->get(ETag::NOT_MODIFIED, false),
                'ip' => $request->ip(),
            ]);

            // "Last activity" in the admin; written at most once a minute.
            $partner = $client->partner;
            if (! $partner->last_activity_at || $partner->last_activity_at->lt(now()->subMinute())) {
                $partner->forceFill(['last_activity_at' => now()])->saveQuietly();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
