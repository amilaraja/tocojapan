<?php

namespace App\Modules\PartnerApi\Http\Middleware;

use App\Modules\PartnerApi\Support\PartnerApi;
use App\Modules\PartnerApi\Support\PartnerApiException;
use Closure;
use Illuminate\Http\Request;
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
}
