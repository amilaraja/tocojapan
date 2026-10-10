<?php

namespace App\Modules\PartnerApi\Http\Middleware;

use App\Modules\PartnerApi\Domain\Usage\Quota;
use App\Modules\PartnerApi\Models\PartnerAccessToken;
use App\Modules\PartnerApi\Support\ETag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-minute and per-day limits on authenticated API calls (SRS §8.3).
 * A 304 answer does not count against the daily quota.
 */
class EnforceLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var PartnerAccessToken $token */
        $token = $request->attributes->get(AuthenticatePartner::TOKEN);
        $partner = $token->client->partner;

        $headers = Quota::enforceMinute($token->client, $partner);
        Quota::ensureDaily($partner, 'api');

        $response = $next($request);

        if (! $request->attributes->get(ETag::NOT_MODIFIED) && $response->getStatusCode() < 500) {
            Quota::countDaily($partner, 'api');
        }

        $response->headers->add($headers + [
            'X-Daily-Limit' => (string) Quota::dailyLimit($partner, 'api'),
            'X-Daily-Remaining' => (string) max(0, Quota::dailyLimit($partner, 'api') - Quota::usedToday($partner, 'api')),
        ]);

        return $response;
    }
}
