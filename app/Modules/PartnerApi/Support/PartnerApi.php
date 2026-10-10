<?php

namespace App\Modules\PartnerApi\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Small helpers shared by the partner middleware, controllers and errors.
 */
class PartnerApi
{
    public const REQUEST_ID = 'partner_api.request_id';

    /** The PartnerClient behind this request, once known (for usage logging). */
    public const CLIENT = 'partner_api.client';

    /** api | image */
    public const KIND = 'partner_api.kind';

    public static function isPartnerRequest(Request $request): bool
    {
        $host = config('partner_api.host');
        if ($host && $request->getHost() !== $host) {
            return false;
        }

        $prefix = trim((string) config('partner_api.prefix'), '/');

        return $prefix !== '' && $request->is($prefix, $prefix.'/*');
    }

    public static function requestId(Request $request): string
    {
        if (! $request->attributes->has(self::REQUEST_ID)) {
            $request->attributes->set(self::REQUEST_ID, 'req_'.Str::lower((string) Str::ulid()));
        }

        return $request->attributes->get(self::REQUEST_ID);
    }

    /** Headers every partner response carries, success or error. */
    public static function finalize(Request $request, Response $response): Response
    {
        $response->headers->set('X-Request-Id', self::requestId($request));
        // Responses are per partner: never let LiteSpeed's page cache keep one.
        $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
        if (! $response->headers->has('Cache-Control') || $response->headers->get('Cache-Control') === 'no-cache, private') {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /** Absolute base of the partner API, e.g. https://api.tocojapan.com/partner/v1. */
    public static function baseUrl(): string
    {
        $host = config('partner_api.host');
        $root = $host ? 'https://'.$host : rtrim((string) config('app.url'), '/');

        return $root.'/'.trim((string) config('partner_api.prefix'), '/');
    }

    public static function docsUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/partner/docs';
    }
}
