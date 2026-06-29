<?php

use App\Models\Country;
use App\Models\Make;
use App\Models\Port;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Settings\CifSettings;

beforeEach(function () {
    $settings = app(CifSettings::class);
    $settings->marine_insurance_usd = 35.0;
    $settings->default_currency = 'USD';
    $settings->price_on_request_default = false;
    $settings->save();

    $this->country = Country::create(['iso2' => 'LK', 'name' => 'Sri Lanka', 'slug' => 'sri-lanka', 'is_active' => true]);
    $this->port = Port::create([
        'country_id' => $this->country->id,
        'name' => 'Colombo',
        'slug' => 'colombo',
        'rate_per_m3' => 25.0,
        'is_active' => true,
    ]);
});

it('calculates CIF from manual price + m3 with a flat insurance fee', function () {
    $this->postJson('/api/v1/cif/calculate', [
        'port_id' => $this->port->id,
        'price_fob' => 5000,
        'm3' => 10,
    ])
        ->assertOk()
        ->assertJsonPath('data.freight', 250) // 10 * 25
        ->assertJsonPath('data.insurance', 35) // flat
        ->assertJsonPath('data.cif_total', 5285)
        ->assertJsonPath('data.port.name', 'Colombo');
});

it('calculates CIF from a vehicle slug', function () {
    $make = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'corolla', 'name' => 'Corolla']);
    $vehicle = Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'price_fob' => 5000,
        'price_on_request' => false,
        'm3' => 10,
        'currency' => 'USD',
        'slug' => 'cif-test-vehicle',
    ]);

    $this->postJson('/api/v1/cif/calculate', [
        'port_id' => $this->port->id,
        'vehicle_slug' => $vehicle->slug,
    ])
        ->assertOk()
        ->assertJsonPath('data.cif_total', 5285)
        ->assertJsonPath('meta.vehicle.slug', 'cif-test-vehicle');
});

it('rejects price-on-request vehicles', function () {
    $make = Make::create(['slug' => 'lexus', 'name' => 'Lexus']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'lx', 'name' => 'LX']);
    $vehicle = Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'price_on_request' => true,
        'price_fob' => 0,
        'm3' => 14,
        'slug' => 'pricey-lx',
    ]);

    $this->postJson('/api/v1/cif/calculate', [
        'port_id' => $this->port->id,
        'vehicle_slug' => $vehicle->slug,
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.fields.vehicle_slug', ['Price is on request.']);
});

it('rejects when neither vehicle nor manual fields are provided', function () {
    $this->postJson('/api/v1/cif/calculate', [
        'port_id' => $this->port->id,
    ])->assertStatus(422);
});
