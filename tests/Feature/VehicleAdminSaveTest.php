<?php

use App\Models\Make;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->make = Make::create(['slug' => 'citroen', 'name' => 'CITROEN']);
    $this->model = VehicleModel::create(['make_id' => $this->make->id, 'slug' => 'berlingo', 'name' => 'Berlingo']);
});

it('gives a second car with the same title its own URL instead of crashing', function () {
    $attrs = ['make_id' => $this->make->id, 'vehicle_model_id' => $this->model->id, 'title' => '2025 CITROEN Berlingo Long'];
    $first = Vehicle::factory()->create($attrs + ['slug' => '2025-citroen-berlingo-long', 'stock_no' => 'E02061']);
    $first->delete();   // trashed vehicles still own their slug

    $second = Vehicle::factory()->create($attrs + ['slug' => '2025-citroen-berlingo-long', 'stock_no' => 'E02070']);
    $third = Vehicle::factory()->create($attrs + ['slug' => '2025-citroen-berlingo-long', 'stock_no' => null]);

    expect($second->slug)->toBe('2025-citroen-berlingo-long-e02070')
        ->and($third->slug)->toBe('2025-citroen-berlingo-long-2');

    // Re-saving keeps the slug.
    $second->update(['price_fob' => 9999]);
    expect($second->fresh()->slug)->toBe('2025-citroen-berlingo-long-e02070');
});

it('lists hand-entered supplier vehicles by default, hides bulk feed stock', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $asnet = Supplier::query()->create(['name' => 'As Net', 'slug' => 'Asnet', 'is_own_stock' => false, 'sort_priority' => 50]);
    $oneprice = Supplier::query()->where('slug', 'oneprice')->value('id');
    $base = ['make_id' => $this->make->id, 'vehicle_model_id' => $this->model->id];
    Vehicle::factory()->create($base + ['title' => 'Volvo From AsNet', 'supplier_id' => $asnet->id, 'supplier_ref' => 'A1']);
    Vehicle::factory()->create($base + ['title' => 'Feed Car OnePrice', 'supplier_id' => $oneprice, 'supplier_ref' => '9']);
    Vehicle::factory()->create($base + ['title' => 'Toco Yard Car']);

    $this->actingAs(User::factory()->create()->assignRole('admin'))
        ->get('/admin/vehicles')->assertOk()
        ->assertSee('Volvo From AsNet')->assertSee('Toco Yard Car')->assertDontSee('Feed Car OnePrice');
});
