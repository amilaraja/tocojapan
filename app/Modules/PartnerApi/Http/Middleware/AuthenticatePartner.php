<?php

namespace App\Modules\PartnerApi\Http\Middleware;

use App\Modules\PartnerApi\Models\PartnerAccessToken;
use App\Modules\PartnerApi\Support\PartnerApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token check for every partner endpoint except the token exchange.
 * Status, contract dates and IP allow-list are re-checked on every call, so
 * a suspend or revoke takes effect on the next request (§8.7).
 */
class AuthenticatePartner
{
    public const TOKEN = 'partner_api.token';

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? PartnerAccessToken::findByPlainToken($plain) : null;

        if (! $token || $token->expires_at->isPast() || ! $token->client?->isActive()) {
            throw new PartnerApiException(401, 'invalid_token', 'Missing, invalid or expired access token.',
                'Request a new token from POST /oauth/token.', ['WWW-Authenticate' => 'Bearer']);
        }

        $partner = $token->client->partner;

        if ($code = $partner->accessDenialCode()) {
            throw self::denied($code);
        }

        if (! $partner->allowsIp($request->ip())) {
            throw self::denied('ip_not_allowed');
        }

        $request->attributes->set(self::TOKEN, $token);

        return $next($request);
    }

    public static function denied(string $code): PartnerApiException
    {
        $titles = [
            'partner_revoked' => 'This partner account has been closed.',
            'partner_suspended' => 'This partner account is suspended.',
            'contract_not_started' => 'The partner contract has not started yet.',
            'contract_ended' => 'The partner contract has ended.',
            'ip_not_allowed' => 'Calls from this IP address are not allowed for this partner.',
        ];

        return new PartnerApiException(403, $code, $titles[$code] ?? 'Access denied.', 'Contact Toco to resolve this.');
    }
}
