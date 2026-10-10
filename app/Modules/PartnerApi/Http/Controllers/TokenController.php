<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Modules\PartnerApi\Domain\Auth\ClientSecret;
use App\Modules\PartnerApi\Domain\Auth\Scopes;
use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Models\PartnerClient;
use App\Modules\PartnerApi\Support\PartnerApi;
use App\Modules\PartnerApi\Support\PartnerApiException;
use App\Modules\PartnerApi\Support\StrictInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OAuth 2.0 client-credentials exchange (RFC 6749 §4.4, SRS §8.1).
 * Credentials come as HTTP Basic or as client_id / client_secret fields.
 * Error bodies are problem+json and also carry the RFC 6749 "error" member,
 * so standard OAuth client libraries understand them.
 */
class TokenController
{
    public function __invoke(Request $request): JsonResponse
    {
        StrictInput::only($request, ['grant_type', 'client_id', 'client_secret', 'scope']);

        $limiterKey = 'partner-api:token-failures:'.$request->ip();
        $maxFailures = (int) config('partner_api.auth.token_attempts_per_minute');
        if (RateLimiter::tooManyAttempts($limiterKey, $maxFailures)) {
            $retry = RateLimiter::availableIn($limiterKey);
            throw new PartnerApiException(429, 'too_many_requests', 'Too many failed token requests.',
                'Wait before trying again.', ['Retry-After' => (string) $retry]);
        }

        if ($request->input('grant_type') !== 'client_credentials') {
            throw self::oauthError(400, 'unsupported_grant_type', 'Only grant_type=client_credentials is supported.');
        }

        [$clientId, $secret] = $this->credentials($request);
        if ($clientId === '' || $secret === '') {
            throw self::oauthError(400, 'invalid_request', 'Send client_id and client_secret, as HTTP Basic or form fields.');
        }

        $client = PartnerClient::with('partner')->where('client_id', $clientId)->first();

        // Same answer for unknown client, wrong secret and revoked key.
        if (! $client || ! $client->isActive() || ! $client->verifySecret($secret)) {
            RateLimiter::hit($limiterKey, 60);
            throw self::oauthError(401, 'invalid_client', 'Client authentication failed.', ['WWW-Authenticate' => 'Basic realm="toco-partner-api"']);
        }

        $partner = $client->partner;
        $request->attributes->set(PartnerApi::CLIENT, $client);
        if ($code = $partner->accessDenialCode()) {
            throw AuthenticatePartner::denied($code);
        }
        if (! $partner->allowsIp($request->ip())) {
            throw AuthenticatePartner::denied('ip_not_allowed');
        }

        $scopes = Scopes::resolve($partner, $request->input('scope'));
        if ($scopes === null) {
            throw self::oauthError(400, 'invalid_scope', 'A requested scope is not available to this partner.');
        }

        $ttl = (int) config('partner_api.auth.token_ttl_seconds');
        $plain = ClientSecret::generateAccessToken();

        $client->accessTokens()->create([
            'token_hash' => hash('sha256', $plain),
            'scopes' => $scopes,
            'ip' => $request->ip(),
            'expires_at' => now()->addSeconds($ttl),
        ]);
        $client->forceFill(['last_used_at' => now()])->save();

        return new JsonResponse([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'scope' => implode(' ', $scopes),
        ], 200, ['Pragma' => 'no-cache']);
    }

    /** @return array{0: string, 1: string} */
    private function credentials(Request $request): array
    {
        if ($request->getUser() !== null) {
            return [urldecode((string) $request->getUser()), urldecode((string) $request->getPassword())];
        }

        return [(string) $request->input('client_id', ''), (string) $request->input('client_secret', '')];
    }

    /** @param  array<string, string>  $headers */
    private static function oauthError(int $status, string $error, string $title, array $headers = []): PartnerApiException
    {
        return new PartnerApiException($status, $error, $title, headers: $headers, extra: ['error' => $error]);
    }
}
