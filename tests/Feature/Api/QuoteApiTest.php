<?php

use App\Models\Country;
use App\Models\Make;
use App\Models\Port;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->country = Country::create(['iso2' => 'LK', 'name' => 'Sri Lanka', 'slug' => 'sri-lanka', 'is_active' => true]);
    $this->port = Port::create(['country_id' => $this->country->id, 'name' => 'Colombo', 'slug' => 'colombo', 'rate_per_m3' => 25, 'is_active' => true]);

    $make = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'corolla', 'name' => 'Corolla']);
    $this->vehicle = Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'slug' => 'quote-test',
    ]);
});

it('requires auth on quote endpoints', function () {
    $this->getJson('/api/v1/quotes')->assertStatus(401);
    $this->postJson('/api/v1/quotes')->assertStatus(401);
});

it('creates a quote and lists it for the owner', function () {
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/quotes', [
            'vehicle_slug' => 'quote-test',
            'country_id' => $this->country->id,
            'port_id' => $this->port->id,
            'contact_name' => 'Jane Buyer',
            'contact_email' => 'jane@example.com',
            'message' => 'Please quote CIF Colombo for this car.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.vehicle.slug', 'quote-test')
        ->assertJsonPath('data.destination.port', 'Colombo');

    $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/quotes')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects a port that does not belong to the country', function () {
    $other = Country::create(['iso2' => 'KE', 'name' => 'Kenya', 'slug' => 'kenya', 'is_active' => true]);
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/quotes', [
            'country_id' => $other->id,
            'port_id' => $this->port->id, // belongs to LK, not KE
            'contact_name' => 'Jane',
            'contact_email' => 'jane@example.com',
        ])
        ->assertStatus(422);
});

it('blocks reading another customer’s quote', function () {
    $a = User::factory()->create()->assignRole('customer');
    $b = User::factory()->create()->assignRole('customer');
    $tokenB = $b->createToken('t')->plainTextToken;

    $quote = Quote::create([
        'reference' => 'Q-FOREIGN',
        'user_id' => $a->id,
        'contact_name' => 'A',
        'contact_email' => 'a@example.com',
        'status' => 'submitted',
    ]);

    $this->withHeader('Authorization', "Bearer $tokenB")
        ->getJson('/api/v1/quotes/'.$quote->id)
        ->assertStatus(403);
});

it('allows the owner to reply to their quote', function () {
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('t')->plainTextToken;

    $quote = Quote::create([
        'user_id' => $user->id,
        'contact_name' => $user->name,
        'contact_email' => $user->email,
        'status' => 'submitted',
    ]);

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/quotes/'.$quote->id.'/messages', ['body' => 'Any update?'])
        ->assertCreated()
        ->assertJsonPath('data.messages.0.body', 'Any update?')
        ->assertJsonPath('data.messages.0.from_admin', false);
});
