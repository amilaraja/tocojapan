<?php

use App\Models\Make;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\BodyTypeSeeder;

beforeEach(function () {
    $this->seed(BodyTypeSeeder::class);
});

it('shows the public vehicle listing', function () {
    $make = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'corolla', 'name' => 'Corolla']);
    Vehicle::factory()->count(3)->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/vehicles')
        ->assertOk()
        ->assertSee('Toyota Corolla');
});

it('hides draft vehicles from the listing', function () {
    $make = Make::create(['slug' => 'nissan', 'name' => 'Nissan']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'note', 'name' => 'Note']);
    Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'draft',
        'title' => 'Hidden Note',
    ]);

    $this->get('/vehicles')
        ->assertOk()
        ->assertDontSee('Hidden Note');
});

it('filters listing by make slug', function () {
    $toyota = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $nissan = Make::create(['slug' => 'nissan', 'name' => 'Nissan']);
    $tModel = VehicleModel::create(['make_id' => $toyota->id, 'slug' => 'corolla', 'name' => 'Corolla']);
    $nModel = VehicleModel::create(['make_id' => $nissan->id, 'slug' => 'note', 'name' => 'Note']);

    Vehicle::factory()->create(['make_id' => $toyota->id, 'vehicle_model_id' => $tModel->id, 'status' => 'published', 'published_at' => now()->subDay(), 'title' => 'Toyota One']);
    Vehicle::factory()->create(['make_id' => $nissan->id, 'vehicle_model_id' => $nModel->id, 'status' => 'published', 'published_at' => now()->subDay(), 'title' => 'Nissan One']);

    $this->get('/vehicles?make=toyota')
        ->assertOk()
        ->assertSee('Toyota One')
        ->assertDontSee('Nissan One');
});

it('shows the public vehicle detail page', function () {
    $make = Make::create(['slug' => 'mazda', 'name' => 'Mazda']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'demio', 'name' => 'Demio']);
    $vehicle = Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'slug' => 'mazda-demio-2018-tj-test',
        'title' => '2018 Mazda Demio',
        'ref_no' => 'TJ-TEST',
    ]);

    $this->get('/vehicles/'.$vehicle->slug)
        ->assertOk()
        ->assertSee('2018 Mazda Demio')
        ->assertSee('TJ-TEST');
});

it('returns 404 for a non-published vehicle detail', function () {
    $make = Make::create(['slug' => 'mazda', 'name' => 'Mazda']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'demio', 'name' => 'Demio']);
    $vehicle = Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'draft',
        'slug' => 'draft-vehicle',
    ]);

    $this->get('/vehicles/'.$vehicle->slug)->assertNotFound();
});
