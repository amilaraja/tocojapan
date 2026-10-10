<?php

use App\Models\BodyType;
use App\Models\Country;
use App\Models\Make;
use App\Models\Port;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Models\VehicleModel;
use App\Modules\PartnerApi\Http\Resources\PartnerVehicleResource;
use App\Modules\PartnerApi\Models\Partner;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use Tests\PartnerApi\Support\Partners;

/*
 * Read API: GET /vehicles, /vehicles/{stock_no}, /catalog/*, POST /cif.
 * TOC-PAPI-FR-STK-01..05, FR-SYNC-01, FR-CAT-01, FR-CIF-01.
 */

beforeEach(function () {
    config(['partner_api.enabled' => true]);
    $this->toyota = Make::create(['slug' => 'toyota', 'name' => 'Toyota', 'is_active' => true]);
    $this->nissan = Make::create(['slug' => 'nissan', 'name' => 'Nissan', 'is_active' => true]);
    $this->hiace = VehicleModel::create(['make_id' => $this->toyota->id, 'slug' => 'hiace-van', 'name' => 'Hiace Van', 'is_active' => true]);
    $this->van = BodyType::create(['slug' => 'van', 'name' => 'Van', 'is_active' => true]);
});

function stockCar(array $attributes = []): Vehicle
{
    static $n = 2000;
    $n++;

    return Vehicle::factory()->create($attributes + [
        'stock_no' => 'E0'.$n, 'status' => 'published', 'price_fob' => 10000, 'price_fob_discount' => null,
        'price_on_request' => false, 'm3' => 12.5, 'year_first_reg' => 2019,
    ]);
}

function partnerGet(string $path, ?Partner $partner = null, array $headers = [])
{
    [$client] = Partners::client($partner ?? Partners::make());

    return test()->withToken(Partners::token($client))->withHeaders($headers)->getJson(Partners::url($path));
}

it('lists own stock that is available or under offer, never supplier, draft or sold stock (FR-STK-01)', function () {
    $available = stockCar(['make_id' => $this->toyota->id]);
    $reserved = stockCar();
    app(HoldService::class)->claim($reserved, new HoldClaim(VehicleHold::CHANNEL_ADMIN, 'JWT buyer'));
    stockCar(['status' => 'draft']);
    stockCar(['status' => 'sold']);
    $supplier = Supplier::create(['name' => 'Feed Co', 'slug' => 'feed-co-test', 'is_own_stock' => false]);
    stockCar(['supplier_id' => $supplier->id]);

    $response = partnerGet('vehicles')->assertOk()
        ->assertJsonPath('meta.sync', 'full')
        ->assertJsonPath('meta.scope_version', 1)
        ->assertHeader('ETag');

    expect(collect($response->json('data'))->pluck('status', 'stock_no')->all())
        ->toBe([$available->stock_no => 'available', $reserved->stock_no => 'reserved']);
});

it('narrows to the partner scope at query level, and hides the rest as 404 (FR-STK-02, §8.2)', function () {
    $toyota = stockCar(['make_id' => $this->toyota->id, 'body_type_id' => $this->van->id, 'price_fob' => 15000]);
    $nissan = stockCar(['make_id' => $this->nissan->id]);
    $dear = stockCar(['make_id' => $this->toyota->id, 'body_type_id' => $this->van->id, 'price_fob' => 40000]);
    $partner = Partners::make(['stock_scope' => ['makes' => [$this->toyota->id], 'body_types' => [$this->van->id], 'price_max' => 20000]]);

    expect(collect(partnerGet('vehicles', $partner)->json('data'))->pluck('stock_no')->all())->toBe([$toyota->stock_no]);

    partnerGet('vehicles/'.$nissan->stock_no, $partner)->assertNotFound()->assertJsonPath('code', 'not_found');
    partnerGet('vehicles/'.$dear->stock_no, $partner)->assertNotFound();
    partnerGet('vehicles/'.$toyota->stock_no, $partner)->assertOk()->assertJsonPath('data.stock_no', $toyota->stock_no);
});

it('sends only whitelisted fields, never supplier cost or ids (FR-STK-03)', function () {
    $car = stockCar(['source_price' => 777777, 'supplier_ref' => 'SECRET-REF', 'supplier_meta' => ['cost' => 1],
        'description' => '<p>Clean van</p>', 'chassis_number' => 'KDH201-0001']);

    $standard = partnerGet('vehicles/'.$car->stock_no)->assertOk()->json('data');
    expect(array_keys($standard))->toBe(PartnerVehicleResource::STANDARD);

    $extended = partnerGet('vehicles/'.$car->stock_no, Partners::make(['field_set' => 'extended']))->assertOk();
    expect(array_keys($extended->json('data')))->toBe([...PartnerVehicleResource::STANDARD, ...PartnerVehicleResource::EXTENDED])
        ->and($extended->json('data.description'))->toBe('Clean van')
        ->and($extended->json('data.chassis_number'))->toBe('KDH201-0001');

    $raw = $extended->getContent();
    foreach (['777777', 'SECRET-REF', 'source_price', 'supplier', 'supplier_id', '"id"'] as $secret) {
        expect($raw)->not->toContain($secret);
    }
});

it('prices per partner mode and never shows the base price under a markup (FR-STK-04)', function (array $settings, ?float $amount, ?string $reason) {
    $car = stockCar(['price_fob' => 10000, 'price_fob_discount' => 9000]);

    $price = partnerGet('vehicles/'.$car->stock_no, Partners::make($settings))->assertOk()->json('data.price');

    expect($price['amount'])->toEqual($amount)->and($price['reason'])->toBe($reason)
        ->and($price['on_request'])->toBe($reason !== null)->and($price['basis'])->toBe('FOB');
})->with([
    'as is (discounted price)' => [['price_mode' => 'as_is'], 9000, null],
    '+8%' => [['price_mode' => 'percent', 'price_markup' => 8], 9720, null],
    '+500' => [['price_mode' => 'fixed', 'price_markup' => 500], 9500, null],
    'on request' => [['price_mode' => 'on_request'], null, 'partner_price_on_request'],
]);

it('marks Toco price-on-request cars with a reason', function () {
    $car = stockCar(['price_on_request' => true]);

    partnerGet('vehicles/'.$car->stock_no)->assertJsonPath('data.price.amount', null)
        ->assertJsonPath('data.price.reason', 'price_on_request');
});

it('filters price in the partner\'s own prices', function () {
    $cheap = stockCar(['price_fob' => 10000]);   // +10% = 11,000
    stockCar(['price_fob' => 20000]);            // +10% = 22,000
    $partner = Partners::make(['price_mode' => 'percent', 'price_markup' => 10]);

    expect(collect(partnerGet('vehicles?price_max=11000', $partner)->json('data'))->pluck('stock_no')->all())->toBe([$cheap->stock_no]);
    partnerGet('vehicles?price_max=1', Partners::make(['price_mode' => 'on_request']))->assertStatus(400)
        ->assertJsonPath('code', 'price_filter_unavailable');
});

it('links buyers back with the partner attribution code (FR-STK-05)', function () {
    $car = stockCar();
    $partner = Partners::make();

    expect(partnerGet('vehicles/'.$car->stock_no, $partner)->json('data.detail_url'))
        ->toBe(rtrim(config('app.url'), '/').'/vehicles/'.$car->slug.'?ref='.$partner->attribution_code);
});

it('pages with an opaque cursor and rejects a tampered one', function () {
    foreach (range(1, 5) as $i) {
        stockCar();
    }
    $partner = Partners::make();
    [$client] = Partners::client($partner);
    $token = Partners::token($client);

    $first = $this->withToken($token)->getJson(Partners::url('vehicles?limit=2'))->assertOk();
    $seen = collect($first->json('data'))->pluck('stock_no');
    $cursor = $first->json('meta.next_cursor');
    while ($cursor) {
        $page = $this->withToken($token)->getJson(Partners::url('vehicles?limit=2&cursor='.urlencode($cursor)))->assertOk();
        $seen = $seen->merge(collect($page->json('data'))->pluck('stock_no'));
        $cursor = $page->json('meta.next_cursor');
    }
    expect($seen->unique()->count())->toBe(5);

    $this->withToken($token)->getJson(Partners::url('vehicles?cursor=bm9wZQ'))->assertStatus(400)->assertJsonPath('code', 'invalid_cursor');
    $this->withToken($token)->getJson(Partners::url('vehicles?limit=500'))->assertStatus(422)->assertJsonPath('code', 'validation_failed');
});

it('reports changes, sold cars and tombstones in a change sync (FR-SYNC-01)', function () {
    $partner = Partners::make(['stock_scope' => ['makes' => [$this->toyota->id]]]);
    $sold = stockCar(['make_id' => $this->toyota->id]);
    $deleted = stockCar(['make_id' => $this->toyota->id]);
    $leaves = stockCar(['make_id' => $this->toyota->id]);
    $untouched = stockCar(['make_id' => $this->toyota->id]);
    $neverShown = stockCar(['make_id' => $this->toyota->id, 'status' => 'draft', 'published_at' => null]);

    $this->travel(10)->minutes();
    $since = now()->toIso8601ZuluString();
    $this->travel(1)->minutes();

    $sold->update(['status' => 'sold']);
    $deleted->delete();
    $leaves->update(['make_id' => $this->nissan->id]);
    $neverShown->update(['title' => 'Still a draft']);

    $data = collect(partnerGet('vehicles?updated_since='.$since, $partner)->assertOk()
        ->assertJsonPath('meta.sync', 'changes')->json('data'))->keyBy('stock_no');

    expect($data->keys()->sort()->values()->all())->toBe(collect([$sold->stock_no, $deleted->stock_no, $leaves->stock_no])->sort()->values()->all())
        ->and($data[$sold->stock_no]['status'])->toBe('sold')
        ->and($data[$deleted->stock_no])->toBe(['stock_no' => $deleted->stock_no, 'status' => 'removed', 'updated_at' => $data[$deleted->stock_no]['updated_at']])
        ->and($data[$leaves->stock_no]['status'])->toBe('removed')
        ->and($data->has($untouched->stock_no))->toBeFalse();

    partnerGet('vehicles?updated_since='.now()->subDays(31)->toIso8601ZuluString(), $partner)
        ->assertStatus(400)->assertJsonPath('code', 'sync_window_exceeded');
});

it('shows a hold as reserved at once and as available again after release (FR-HLD-02)', function () {
    $car = stockCar();
    $partner = Partners::make();
    $since = now()->subMinute()->toIso8601ZuluString();
    $this->travel(1)->minutes();

    $hold = app(HoldService::class)->claim($car, new HoldClaim(VehicleHold::CHANNEL_ADMIN, 'JWT buyer'));
    partnerGet('vehicles/'.$car->stock_no, $partner)->assertJsonPath('data.status', 'reserved');
    partnerGet('vehicles?updated_since='.$since, $partner)->assertJsonPath('data.0.status', 'reserved');

    app(HoldService::class)->release($hold, 'Buyer walked away');
    partnerGet('vehicles/'.$car->stock_no, $partner)->assertJsonPath('data.status', 'available');
});

it('bumps scope_version when the admin changes what the partner sees', function () {
    $partner = Partners::make();
    $partner->update(['price_mode' => 'percent', 'price_markup' => 5]);
    $partner->update(['notes' => 'Called on Monday']);

    expect($partner->fresh()->scope_version)->toBe(2);
});

it('answers 304 to a matching If-None-Match', function () {
    stockCar();
    $partner = Partners::make();
    [$client] = Partners::client($partner);
    $token = Partners::token($client);

    $etag = $this->withToken($token)->getJson(Partners::url('vehicles'))->assertOk()->headers->get('ETag');
    $this->withToken($token)->withHeaders(['If-None-Match' => $etag])->getJson(Partners::url('vehicles'))->assertStatus(304);
});

it('needs the stock:read scope', function () {
    [$client] = Partners::client();
    $this->withToken(Partners::token($client, ['cif:read']))->getJson(Partners::url('vehicles'))
        ->assertForbidden()->assertJsonPath('code', 'insufficient_scope');
});

it('serves reference lists with in-scope counts (FR-CAT-01)', function () {
    stockCar(['make_id' => $this->toyota->id, 'vehicle_model_id' => $this->hiace->id, 'body_type_id' => $this->van->id]);
    $ke = Country::create(['iso2' => 'KE', 'name' => 'Kenya', 'slug' => 'kenya', 'is_active' => true]);
    Port::create(['country_id' => $ke->id, 'name' => 'Mombasa', 'slug' => 'mombasa', 'rate_per_m3' => 40, 'is_active' => true]);
    $partner = Partners::make();

    expect(collect(partnerGet('catalog/makes', $partner)->json('data'))->firstWhere('slug', 'toyota')['vehicle_count'])->toBe(1);
    partnerGet('catalog/models?make=toyota', $partner)->assertJsonPath('data.0.slug', 'hiace-van')->assertJsonPath('data.0.vehicle_count', 1);
    partnerGet('catalog/body-types', $partner)->assertJsonPath('data.0.vehicle_count', 1);
    partnerGet('catalog/countries', $partner)->assertJsonPath('data.0.code', 'KE');
    partnerGet('catalog/ports?country=ke', $partner)->assertJsonPath('data.0.name', 'Mombasa');
});

it('estimates CIF on the partner price (FR-CIF-01)', function () {
    $car = stockCar(['price_fob' => 10000, 'm3' => 10]);
    $ke = Country::create(['iso2' => 'KE', 'name' => 'Kenya', 'slug' => 'kenya', 'is_active' => true]);
    $port = Port::create(['country_id' => $ke->id, 'name' => 'Mombasa', 'slug' => 'mombasa', 'rate_per_m3' => 40, 'is_active' => true]);
    [$client] = Partners::client(Partners::make(['price_mode' => 'fixed', 'price_markup' => 500]));
    $token = Partners::token($client);

    $data = $this->withToken($token)->postJson(Partners::url('cif'), ['stock_no' => $car->stock_no, 'port_id' => $port->id])
        ->assertOk()->json('data');
    expect($data['fob'])->toEqual(10500)->and($data['freight'])->toEqual(400)
        ->and($data['cif'])->toEqual(10500 + 400 + $data['insurance']);

    $this->withToken($token)->postJson(Partners::url('cif'), ['stock_no' => $car->stock_no, 'port_id' => 999])
        ->assertStatus(422)->assertJsonPath('code', 'unknown_port');
    $this->withToken($token)->postJson(Partners::url('cif'), ['stock_no' => 'NOPE', 'port_id' => $port->id])->assertNotFound();
});
