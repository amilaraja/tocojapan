<?php

namespace App\Modules\PartnerApi\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** ETag / If-None-Match on lists and single vehicles (SRS §6 conventions). */
class ETag
{
    public const NOT_MODIFIED = 'partner_api.not_modified';

    /** @param  array<string, mixed>  $body */
    public static function respond(Request $request, array $body): Response
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $etag = 'W/"'.substr(hash('sha256', $json), 0, 32).'"';

        $sent = array_map('trim', explode(',', (string) $request->header('If-None-Match')));
        if (in_array($etag, $sent, true) || in_array('*', $sent, true)) {
            // Flag for the usage counter: a 304 does not count against the daily quota.
            $request->attributes->set(self::NOT_MODIFIED, true);

            return (new Response('', 304))->setEtag(substr($etag, 3, -1), true);
        }

        $response = JsonResponse::fromJsonString($json);
        $response->headers->set('ETag', $etag);

        return $response;
    }
}
