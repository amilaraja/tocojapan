<?php

use App\Modules\PartnerApi\Http\Middleware\PartnerBoundary;
use App\Modules\PartnerApi\Models\Partner;
use App\Models\NotFoundLog;
use Illuminate\Support\Facades\Route;
use Tests\PartnerApi\Support\Partners;

beforeEach(fn () => config(['partner_api.enabled' => true]));

it('tells a partner who it is and what its limits are', function () {
    $partner = Partners::make(['type' => Partner::TYPE_LISTING, 'rate_per_day' => 5000]);
    [$client] = Partners::client($partner);
    $token = Partners::token($client);

    $this->withToken($token)->getJson(Partners::url('me'))->assertOk()
        ->assertJsonPath('partner.name', 'JWT Japan')
        ->assertJsonPath('partner.type', 'listing')
        ->assertJsonPath('partner.attribution_code', $partner->attribution_code)
        ->assertJsonPath('client.environment', 'live')
        ->assertJsonPath('limits.requests_per_day', 5000)
        ->assertJsonMissingPath('limits.max_active_holds')
        ->assertHeader('X-Request-Id')
        ->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');
});

it('shows hold limits to selling partners only', function () {
    [$client] = Partners::client(Partners::make(['type' => Partner::TYPE_SELLING]));

    $this->withToken(Partners::token($client))->getJson(Partners::url('me'))->assertOk()
        ->assertJsonPath('limits.max_active_holds', 5)
        ->assertJsonPath('limits.hold_hours', 48);
});

it('rejects missing, unknown and expired tokens', function () {
    $this->getJson(Partners::url('me'))->assertUnauthorized()->assertJsonPath('code', 'invalid_token');
    $this->withToken('tcat_nope')->getJson(Partners::url('me'))->assertUnauthorized();

    $token = Partners::token(ttl: 900);
    $this->travel(16)->minutes();
    $this->withToken($token)->getJson(Partners::url('me'))->assertUnauthorized()->assertJsonPath('code', 'invalid_token');
});

it('stops a live token on the next call after suspend or revoke (TOC-PAPI-§8.7)', function () {
    [$client] = Partners::client();
    $token = Partners::token($client);
    $partner = $client->partner;

    $partner->suspend('Unpaid invoice');
    $this->withToken($token)->getJson(Partners::url('me'))->assertForbidden()->assertJsonPath('code', 'partner_suspended');

    $partner->reactivate();
    $this->withToken($token)->getJson(Partners::url('me'))->assertOk();

    $partner->revoke('Contract terminated');
    $this->withToken($token)->getJson(Partners::url('me'))->assertUnauthorized();
    expect($client->fresh()->status)->toBe('revoked');
});

it('checks the IP allow-list on every call', function () {
    [$client] = Partners::client(Partners::make(['ip_allowlist' => ['203.0.113.9']]));

    $this->withToken(Partners::token($client))->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->getJson(Partners::url('me'))->assertForbidden()->assertJsonPath('code', 'ip_not_allowed');
});

it('requires the route scope (TOC-PAPI-§8.2)', function () {
    Route::middleware([PartnerBoundary::class, 'partner.auth', 'partner.scope:stock:hold'])
        ->get(Partners::url('scope-probe'), fn () => ['ok' => true]);

    $this->withToken(Partners::token(scopes: ['stock:read']))->getJson(Partners::url('scope-probe'))
        ->assertForbidden()->assertJsonPath('code', 'insufficient_scope')->assertJsonPath('required_scope', 'stock:hold');

    $this->withToken(Partners::token(scopes: ['stock:hold']))->getJson(Partners::url('scope-probe'))->assertOk();
});

it('answers unknown partner paths with problem+json and never logs them as site 404s', function () {
    $this->getJson(Partners::url('nothing-here'))->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('X-Request-Id')
        ->assertJsonPath('code', 'not_found');

    $this->postJson(Partners::url('me'))->assertStatus(405)->assertJsonPath('code', 'method_not_allowed');

    expect(NotFoundLog::count())->toBe(0);
});

it('rejects unknown query parameters', function () {
    $this->withToken(Partners::token())->getJson(Partners::url('me').'?verbose=1')
        ->assertStatus(400)->assertJsonPath('code', 'unknown_parameter');
});
