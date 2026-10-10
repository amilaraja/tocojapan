<?php

namespace Tests\PartnerApi\Support;

use App\Modules\PartnerApi\Domain\Auth\ClientSecret;
use App\Modules\PartnerApi\Domain\Auth\Scopes;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerClient;

/** Builders for partner API tests. */
class Partners
{
    /** @param  array<string, mixed>  $attributes */
    public static function make(array $attributes = []): Partner
    {
        return Partner::create($attributes + ['name' => 'JWT Japan', 'country' => 'JP']);
    }

    /** @return array{0: PartnerClient, 1: string} */
    public static function client(?Partner $partner = null, string $environment = 'live'): array
    {
        return PartnerClient::issue($partner ?? self::make(), 'Production', $environment);
    }

    /**
     * A ready access token, bypassing the exchange.
     *
     * @param  list<string>|null  $scopes
     */
    public static function token(?PartnerClient $client = null, ?array $scopes = null, int $ttl = 900): string
    {
        $client ??= self::client()[0];
        $plain = ClientSecret::generateAccessToken();

        $client->accessTokens()->create([
            'token_hash' => hash('sha256', $plain),
            'scopes' => $scopes ?? Scopes::grantable($client->partner),
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return $plain;
    }

    public static function url(string $path): string
    {
        return '/'.trim(config('partner_api.prefix'), '/').'/'.ltrim($path, '/');
    }
}
