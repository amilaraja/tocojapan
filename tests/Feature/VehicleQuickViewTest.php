<?php

use App\Models\Country;
use App\Models\Make;
use App\Models\Port;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;

beforeEach(function () {
    $make = Make::query()->create(['name' => 'SUZUKI', 'slug' => 'suzuki']);
    $model = VehicleModel::query()->create(['make_id' => $make->id, 'name' => 'Carry Truck', 'slug' => 'carry-truck']);
    $this->vehicle = Vehicle::factory()->create([
        'make_id' => $make->id, 'vehicle_model_id' => $model->id, 'title' => '1997 SUZUKI Carry Truck',
        'stock_no' => 'E01948', 'status' => 'published', 'price_fob' => 2100, 'price_on_request' => false,
        'm3' => 8.23, 'chassis_number' => 'DD51B-395385', 'model_code' => 'V-DD51B', 'length_cm' => 339.5, 'width_cm' => 139.5, 'height_cm' => 177,
    ]);
});

it('renders the quick view fragment with the shared spec table', function () {
    $this->get(route('vehicles.quick-view', $this->vehicle->slug))
        ->assertOk()
        ->assertDontSee('<html', false)
        ->assertSee('1997 SUZUKI Carry Truck')
        ->assertSee('DD51B-395385')
        ->assertSee('3.4 × 1.4 × 1.77 m')
        ->assertSee(route('vehicles.show', $this->vehicle->slug));
});

it('404s the quick view for drafts and unknown slugs', function () {
    $this->vehicle->update(['status' => 'draft']);

    $this->get(route('vehicles.quick-view', $this->vehicle->slug))->assertNotFound();
    $this->get(route('vehicles.quick-view', 'no-such-vehicle'))->assertNotFound();
});

it('adds a quick view button to listing rows', function () {
    $this->get(route('vehicles.index'))
        ->assertOk()
        ->assertSee('data-quick-view="'.route('vehicles.quick-view', $this->vehicle->slug).'"', false)
        ->assertSee('Next vehicle')
        ->assertSee('Quick view');
});

it('shows buying options: sign-in prompt for guests, quote link for members', function () {
    $url = route('vehicles.quick-view', $this->vehicle->slug);

    $this->get($url)->assertSee('Buying options')->assertSee('Sign in to buy or quote');

    $this->actingAs(User::factory()->create())->get($url)
        ->assertSee('Buying options')
        ->assertDontSee('Sign in to buy or quote')
        ->assertSee(route('vehicles.show', $this->vehicle->slug).'#quote-form', false);
});

it('hides buy actions for a sold vehicle', function () {
    $this->vehicle->update(['status' => 'sold', 'sold_at' => now()]);

    $this->get(route('vehicles.quick-view', $this->vehicle->slug))
        ->assertSee('Sold — view our available stock')
        ->assertDontSee('Sign in to buy or quote');
});

it('serves CIF destinations (countries, active ports, import rules) as cacheable JSON', function () {
    $tz = Country::query()->create(['iso2' => 'TZ', 'name' => 'Tanzania', 'slug' => 'tanzania', 'is_active' => true]);
    Port::query()->create(['country_id' => $tz->id, 'name' => 'Dar es Salaam', 'slug' => 'dar', 'rate_per_m3' => 100, 'is_active' => true]);
    Port::query()->create(['country_id' => $tz->id, 'name' => 'Closed', 'slug' => 'closed', 'rate_per_m3' => 100, 'is_active' => false]);

    $this->getJson(route('cif.destinations'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=600, public')
        ->assertJsonPath('0.name', 'Tanzania')
        ->assertJsonCount(1, '0.ports')
        ->assertJsonPath('0.ports.0.name', 'Dar es Salaam')
        ->assertJsonPath('0.ports.0.regulation', null);
});

it('embeds a lazy, auto-calculating CIF calculator seeded with the saved port', function () {
    $tz = Country::query()->create(['iso2' => 'TZ', 'name' => 'Tanzania', 'slug' => 'tanzania', 'is_active' => true]);
    $dar = Port::query()->create(['country_id' => $tz->id, 'name' => 'Dar es Salaam', 'slug' => 'dar', 'rate_per_m3' => 100, 'is_active' => true]);

    $html = $this->withCookie('toco_port', (string) $dar->id)
        ->get(route('vehicles.quick-view', $this->vehicle->slug))
        ->assertOk()
        ->assertSee('Estimate landed cost')
        ->assertSee('Change country / port')
        ->getContent();

    preg_match("/cifCalc\\(JSON\\.parse\\('(.*?)'\\)\\)/", $html, $m);
    $cfg = json_decode(json_decode('"'.$m[1].'"'), true);

    expect($cfg)->toMatchArray([
        'prePortId' => $dar->id,
        'preCountryId' => $tz->id,
        'initialPortName' => 'Dar es Salaam',
        'auto' => true,
        'countriesUrl' => route('cif.destinations'),
    ])->and($cfg['initialCif'])->toBeGreaterThan(2100.0)
        ->and($cfg)->not->toHaveKey('countries');
});

it('leaves out the calculator for price-on-request vehicles', function () {
    $this->vehicle->update(['price_on_request' => true]);

    $this->get(route('vehicles.quick-view', $this->vehicle->slug))
        ->assertOk()
        ->assertDontSee('Estimate landed cost')
        ->assertDontSee('cifCalc(', false);
});

it('keeps the detail page calculator inline (countries embedded, manual Calculate)', function () {
    $html = $this->get(route('vehicles.show', $this->vehicle->slug))->assertOk()
        ->assertSee('Estimate landed cost')
        ->getContent();

    expect($html)->toContain('window.cifCalc = function')
        ->and($html)->toContain('\u0022countries\u0022:[');
});
