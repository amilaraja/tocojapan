<?php

use App\Models\Make;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $make = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'corolla', 'name' => 'Corolla']);
    $this->vehicle = Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'slug' => 'fav-test',
    ]);
});

it('requires auth on favorites endpoints', function () {
    $this->getJson('/api/v1/favorites')->assertStatus(401);
    $this->postJson('/api/v1/favorites/fav-test')->assertStatus(401);
    $this->deleteJson('/api/v1/favorites/fav-test')->assertStatus(401);
});

it('toggles a favorite via the API', function () {
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/favorites/fav-test')
        ->assertOk()
        ->assertJsonPath('data.favorited', true);

    expect($user->fresh()->favorites()->count())->toBe(1);

    $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/favorites')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withHeader('Authorization', "Bearer $token")
        ->deleteJson('/api/v1/favorites/fav-test')
        ->assertOk()
        ->assertJsonPath('data.favorited', false);

    expect($user->fresh()->favorites()->count())->toBe(0);
});
