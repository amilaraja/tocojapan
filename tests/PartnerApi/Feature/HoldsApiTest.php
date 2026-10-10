<?php

use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Models\Partner;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use Tests\PartnerApi\Support\Partners;

/*
 * Holds API for selling partners (SRS section 7, Amendment 01).
 * TOC-PAPI-FR-HLD-01/02/07/09, FR-PRT-04.
 */

beforeEach(function () {
    config(['partner_api.enabled' => true]);
    $this->car = Vehicle::factory()->create(['status' => 'published', 'stock_no' => 'E05001', 'price_fob' => 10000]);
});

function holdToken(array $partner = [], string $env = 'live'): string
{
    [$client] = Partners::client(Partners::make($partner + ['type' => 'selling']), $env);

    return Partners::token($client);
}

function placeHold(string $token, string $stockNo = 'E05001', array $body = ['buyer_ref' => 'order-1'], array $headers = [])
{
    return test()->withToken($token)->withHeaders($headers)->postJson(Partners::url("vehicles/{$stockNo}/holds"), $body);
}

it('lets a selling partner hold a car; the first request wins (FR-HLD-01)', function () {
    $token = holdToken(['price_mode' => 'percent', 'price_markup' => 10]);

    $hold = placeHold($token, body: ['buyer_ref' => 'order-1', 'destination_country' => 'ke'])->assertCreated()
        ->assertJsonPath('data.stock_no', 'E05001')->assertJsonPath('data.state', 'active')
        ->assertJsonPath('data.destination_country', 'KE')
        ->assertJsonPath('data.price.amount', 11000)
        ->json('data');

    expect($hold['hold_id'])->toStartWith('hld_')->and($hold['expires_at'])->not->toBeNull()
        ->and($this->car->fresh()->status)->toBe('reserved');

    placeHold(holdToken(['name' => 'Rival']))->assertStatus(409)->assertJsonPath('code', 'vehicle_unavailable');
});

it('replays an Idempotency-Key and returns an existing hold with 200', function () {
    $token = holdToken();
    $first = placeHold($token, headers: ['Idempotency-Key' => 'order-1'])->assertCreated()->json('data.hold_id');

    expect(placeHold($token, headers: ['Idempotency-Key' => 'order-1'])->assertOk()->json('data.hold_id'))->toBe($first)
        ->and(placeHold($token)->assertOk()->json('data.hold_id'))->toBe($first)
        ->and(VehicleHold::count())->toBe(1);
});

it('refuses listing partners, test keys and tokens without stock:hold (Amendment 01)', function () {
    [$listing] = Partners::client(Partners::make(['type' => 'listing']));
    placeHold(Partners::token($listing, ['stock:read', 'stock:hold']))->assertForbidden()
        ->assertJsonPath('code', 'holds_not_available_for_partner');

    placeHold(holdToken(env: 'test'))->assertForbidden()->assertJsonPath('code', 'holds_not_available_for_test_keys');

    [$client] = Partners::client(Partners::make(['type' => 'selling']));
    placeHold(Partners::token($client, ['stock:read']))->assertForbidden()->assertJsonPath('code', 'insufficient_scope');

    expect(VehicleHold::count())->toBe(0);
});

it('hides cars outside the scope and caps active holds (FR-HLD-07)', function () {
    $other = Vehicle::factory()->create(['status' => 'published', 'stock_no' => 'E05002', 'price_fob' => 50000]);
    $token = holdToken(['max_active_holds' => 1, 'stock_scope' => ['price_max' => 20000]]);

    placeHold($token, 'E05002')->assertNotFound();
    placeHold($token)->assertCreated();

    $other->update(['price_fob' => 15000]);
    placeHold($token, 'E05002')->assertStatus(409)->assertJsonPath('code', 'hold_limit_reached');
});

it('lists and releases the partner\'s own holds only', function () {
    $token = holdToken();
    $holdId = placeHold($token)->json('data.hold_id');

    $this->withToken($token)->getJson(Partners::url('holds'))->assertOk()->assertJsonPath('data.0.hold_id', $holdId);

    $this->withToken(holdToken(['name' => 'Rival']))->deleteJson(Partners::url('holds/'.$holdId))->assertNotFound();

    $this->withToken($token)->deleteJson(Partners::url('holds/'.$holdId))->assertOk()->assertJsonPath('data.state', 'released');
    expect($this->car->fresh()->status)->toBe('published');

    $this->withToken($token)->deleteJson(Partners::url('holds/'.$holdId))->assertStatus(409)->assertJsonPath('code', 'hold_not_active');
});

it('rejects unknown body fields and long idempotency keys', function () {
    $token = holdToken();

    placeHold($token, body: ['buyer_ref' => 'x', 'amount' => 1])->assertStatus(400)->assertJsonPath('code', 'unknown_parameter');
    placeHold($token, headers: ['Idempotency-Key' => str_repeat('k', 121)])->assertStatus(400)->assertJsonPath('code', 'invalid_idempotency_key');
    placeHold($token, body: [])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
});

it('shows a car held by Toco staff as unavailable to selling partners', function () {
    app(HoldService::class)->claim($this->car, new HoldClaim(VehicleHold::CHANNEL_ADMIN, 'JWT buyer'));

    placeHold(holdToken())->assertStatus(409)->assertJsonPath('code', 'vehicle_unavailable');
});
