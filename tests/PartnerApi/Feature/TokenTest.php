<?php

use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerAccessToken;
use Tests\PartnerApi\Support\Partners;

beforeEach(fn () => config(['partner_api.enabled' => true]));

function tokenRequest(array $body = [], ?array $basic = null, array $server = [])
{
    $headers = $basic ? ['Authorization' => 'Basic '.base64_encode(rawurlencode($basic[0]).':'.rawurlencode($basic[1]))] : [];

    return test()->flushHeaders()->withHeaders($headers)->withServerVariables($server)
        ->postJson(Partners::url('oauth/token'), $body + ['grant_type' => 'client_credentials']);
}

it('issues a 15-minute token for HTTP Basic credentials (TOC-PAPI-§8.1)', function () {
    [$client, $secret] = Partners::client();

    $response = tokenRequest(basic: [$client->client_id, $secret])->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 900)
        ->assertHeader('X-Request-Id')
        ->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->json('access_token'))->toStartWith('tcat_');

    $stored = PartnerAccessToken::sole();
    expect($stored->token_hash)->toBe(hash('sha256', $response->json('access_token')))
        ->and($stored->expires_at->diffInSeconds(now(), true))->toBeGreaterThan(890)
        ->and($client->fresh()->last_used_at)->not->toBeNull();
});

it('accepts client_id and client_secret as body fields', function () {
    [$client, $secret] = Partners::client();

    tokenRequest(['client_id' => $client->client_id, 'client_secret' => $secret])->assertOk();
});

it('never grants stock:hold to a listing partner (TOC-PAPI-FR-PRT-04)', function () {
    [$client, $secret] = Partners::client(Partners::make(['type' => Partner::TYPE_LISTING]));

    tokenRequest(basic: [$client->client_id, $secret])->assertOk()
        ->assertJsonPath('scope', 'stock:read cif:read usage:read');

    tokenRequest(['scope' => 'stock:read stock:hold'], [$client->client_id, $secret])
        ->assertStatus(400)->assertJsonPath('code', 'invalid_scope')->assertJsonPath('error', 'invalid_scope');
});

it('grants stock:hold to a selling partner, and narrows scopes on request', function () {
    [$client, $secret] = Partners::client(Partners::make(['type' => Partner::TYPE_SELLING]));

    tokenRequest(basic: [$client->client_id, $secret])->assertOk()
        ->assertJsonPath('scope', 'stock:read cif:read usage:read stock:hold');

    tokenRequest(['scope' => 'stock:read'], [$client->client_id, $secret])->assertOk()
        ->assertJsonPath('scope', 'stock:read');
});

it('answers a wrong secret, an unknown client and a revoked key the same way', function () {
    [$client, $secret] = Partners::client();
    [$revoked, $revokedSecret] = Partners::client($client->partner);
    $revoked->revoke();

    foreach ([[$client->client_id, 'tcp_live_wrong'], ['tcc_nobody', $secret], [$revoked->client_id, $revokedSecret]] as $creds) {
        tokenRequest(basic: $creds)->assertStatus(401)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('WWW-Authenticate')
            ->assertJsonPath('code', 'invalid_client')
            ->assertJsonPath('error', 'invalid_client')
            ->assertJsonStructure(['type', 'title', 'status', 'request_id']);
    }
});

it('rejects other grant types, missing credentials and unknown parameters', function () {
    [$client, $secret] = Partners::client();

    tokenRequest(['grant_type' => 'password'], [$client->client_id, $secret])
        ->assertStatus(400)->assertJsonPath('code', 'unsupported_grant_type');

    tokenRequest()->assertStatus(400)->assertJsonPath('code', 'invalid_request');

    tokenRequest(['client_ids' => 'x'], [$client->client_id, $secret])
        ->assertStatus(400)->assertJsonPath('code', 'unknown_parameter')->assertJsonPath('unknown', ['client_ids']);
});

it('refuses suspended, revoked and out-of-contract partners (TOC-PAPI-§8.7)', function (array $attributes, string $code) {
    [$client, $secret] = Partners::client(Partners::make($attributes));

    tokenRequest(basic: [$client->client_id, $secret])->assertForbidden()->assertJsonPath('code', $code);
})->with([
    'suspended' => [['status' => Partner::STATUS_SUSPENDED], 'partner_suspended'],
    'contract ended' => [['contract_ends_on' => now()->subDays(2)->toDateString()], 'contract_ended'],
    'contract not started' => [['contract_starts_on' => now()->addDays(2)->toDateString()], 'contract_not_started'],
]);

it('honours the server IP allow-list (TOC-PAPI-§8.4)', function () {
    [$client, $secret] = Partners::client(Partners::make(['ip_allowlist' => ['203.0.113.0/24']]));

    tokenRequest(basic: [$client->client_id, $secret], server: ['REMOTE_ADDR' => '198.51.100.7'])
        ->assertForbidden()->assertJsonPath('code', 'ip_not_allowed');

    tokenRequest(basic: [$client->client_id, $secret], server: ['REMOTE_ADDR' => '203.0.113.9'])->assertOk();
});

it('keeps the old secret working for 7 days after rotation (TOC-PAPI-FR-PRT-02)', function () {
    [$client, $old] = Partners::client();
    $new = $client->rotate();

    tokenRequest(basic: [$client->client_id, $new])->assertOk();
    tokenRequest(basic: [$client->client_id, $old])->assertOk();

    $this->travel(8)->days();
    tokenRequest(basic: [$client->client_id, $old])->assertStatus(401);
    tokenRequest(basic: [$client->client_id, $new])->assertOk();
});

it('locks out an IP after too many failed attempts', function () {
    config(['partner_api.auth.token_attempts_per_minute' => 3]);
    [$client, $secret] = Partners::client();

    foreach (range(1, 3) as $i) {
        tokenRequest(basic: [$client->client_id, 'wrong'])->assertStatus(401);
    }

    tokenRequest(basic: [$client->client_id, $secret])->assertStatus(429)
        ->assertHeader('Retry-After')->assertJsonPath('code', 'too_many_requests');
});

it('answers 404 on every partner route while the API is switched off', function () {
    config(['partner_api.enabled' => false]);
    [$client, $secret] = Partners::client();

    tokenRequest(basic: [$client->client_id, $secret])->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('refuses request bodies over 64 KB', function () {
    tokenRequest(['client_id' => str_repeat('x', 70 * 1024)])->assertStatus(413)->assertJsonPath('code', 'body_too_large');
});
