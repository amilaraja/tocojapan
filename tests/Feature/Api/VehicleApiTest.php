<?php

use App\Models\Country;
use App\Models\Make;
use App\Models\Port;
use App\Models\Vehicle;
use App\Models\VehicleModel;

it('lists published vehicles via the API', function () {
    $make = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'corolla', 'name' => 'Corolla']);
    Vehicle::factory()->count(3)->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    // A draft that should not appear.
    Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'draft',
    ]);

    $this->getJson('/api/v1/vehicles')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonStructure([
            'data' => [['id', 'slug', 'title', 'price', 'make', 'model']],
            'meta' => ['pagination' => ['total', 'per_page', 'current_page']],
        ]);
});

it('filters API listing by make + year_from', function () {
    $toyota = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $nissan = Make::create(['slug' => 'nissan', 'name' => 'Nissan']);
    $tModel = VehicleModel::create(['make_id' => $toyota->id, 'slug' => 'corolla', 'name' => 'Corolla']);
    $nModel = VehicleModel::create(['make_id' => $nissan->id, 'slug' => 'note', 'name' => 'Note']);

    Vehicle::factory()->create(['make_id' => $toyota->id, 'vehicle_model_id' => $tModel->id, 'status' => 'published', 'published_at' => now()->subDay(), 'year_first_reg' => 2018]);
    Vehicle::factory()->create(['make_id' => $toyota->id, 'vehicle_model_id' => $tModel->id, 'status' => 'published', 'published_at' => now()->subDay(), 'year_first_reg' => 2022]);
    Vehicle::factory()->create(['make_id' => $nissan->id, 'vehicle_model_id' => $nModel->id, 'status' => 'published', 'published_at' => now()->subDay(), 'year_first_reg' => 2022]);

    $this->getJson('/api/v1/vehicles?make=toyota&year_from=2020')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
});

it('returns vehicle detail by slug', function () {
    $make = Make::create(['slug' => 'mazda', 'name' => 'Mazda']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'demio', 'name' => 'Demio']);
    $vehicle = Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'slug' => 'detail-test',
        'title' => '2019 Mazda Demio',
    ]);

    $this->getJson('/api/v1/vehicles/'.$vehicle->slug)
        ->assertOk()
        ->assertJsonPath('data.title', '2019 Mazda Demio')
        ->assertJsonPath('data.make.slug', 'mazda');
});

it('returns 404 for unknown vehicle slug', function () {
    $this->getJson('/api/v1/vehicles/does-not-exist')->assertNotFound();
});

it('lists makes via the API', function () {
    Make::create(['slug' => 'toyota', 'name' => 'Toyota', 'is_active' => true]);
    Make::create(['slug' => 'nissan', 'name' => 'Nissan', 'is_active' => true]);

    $this->getJson('/api/v1/makes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'nissan');
});

it('returns models for a given make', function () {
    $make = Make::create(['slug' => 'honda', 'name' => 'Honda']);
    VehicleModel::create(['make_id' => $make->id, 'slug' => 'fit', 'name' => 'Fit']);
    VehicleModel::create(['make_id' => $make->id, 'slug' => 'civic', 'name' => 'Civic']);

    $this->getJson('/api/v1/makes/honda/models')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.make.name', 'Honda');
});

it('lists countries with their ports', function () {
    $country = Country::create(['iso2' => 'LK', 'name' => 'Sri Lanka', 'slug' => 'sri-lanka', 'is_active' => true]);
    Port::create([
        'country_id' => $country->id,
        'name' => 'Colombo',
        'slug' => 'colombo',
        'rate_per_m3' => 25.0,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/countries')
        ->assertOk()
        ->assertJsonPath('data.0.iso2', 'LK')
        ->assertJsonPath('data.0.ports.0.name', 'Colombo')
        ->assertJsonPath('data.0.ports.0.rate_per_m3', 25);
});
