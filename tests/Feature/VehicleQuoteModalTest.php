<?php

use App\Models\Make;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Support\CifDestinations;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    $make = Make::query()->create(['name' => 'SUZUKI', 'slug' => 'suzuki']);
    $model = VehicleModel::query()->create(['make_id' => $make->id, 'name' => 'Carry Truck', 'slug' => 'carry-truck']);
    $this->vehicle = Vehicle::factory()->create([
        'make_id' => $make->id, 'vehicle_model_id' => $model->id, 'title' => '1997 SUZUKI Carry Truck',
        'status' => 'published', 'price_fob' => 2100, 'price_on_request' => false, 'm3' => 8.23,
    ]);
    $this->user = User::factory()->create();
});

it('renders the quote form as a closed modal opened by the Request a quote button', function () {
    $html = $this->actingAs($this->user)->get(route('vehicles.show', $this->vehicle->slug))->assertOk()
        ->assertSee('Submit quote request')
        ->assertSee('@open-quote.window="show()"', false)
        ->assertSee('@click.prevent="$dispatch(\'open-quote\')"', false)
        ->getContent();

    expect($html)->toMatch('/x-data="\\{\\s*open: false,\\s*show\\(\\)/');
});

it('opens the quote modal straight away when a submission bounced back with errors', function () {
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['contact_email' => ['The contact email field is required.']]));

    $this->actingAs($this->user);
    $html = (string) $this->view('vehicles.show', [
        'vehicle' => $this->vehicle->load(['make', 'vehicleModel', 'bodyType', 'media', 'supplier']),
        'countries' => CifDestinations::countries(),
        'relatedVehicles' => collect(),
        'errors' => $errors,
    ]);

    expect($html)->toContain('The contact email field is required.')
        ->and($html)->toMatch('/x-data="\\{\\s*open: true,\\s*show\\(\\)/');
});
