<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('registers a new customer and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Buyer',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'iphone-15',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'roles'],
                'token',
            ],
            'meta',
            'errors',
        ])
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonPath('data.user.roles', ['customer']);

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('logs in an existing user and issues a token', function () {
    User::factory()->create([
        'email' => 'buyer@example.com',
        'password' => Hash::make('secret123'),
    ])->assignRole('customer');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'buyer@example.com',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.email', 'buyer@example.com')
        ->assertJsonStructure(['data' => ['user', 'token']]);
});

it('rejects login with bad credentials in error envelope', function () {
    User::factory()->create([
        'email' => 'buyer@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'buyer@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.message', 'The provided credentials are incorrect.')
        ->assertJsonPath('data', null);
});

it('returns 401 from /auth/me without a token', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('returns the authenticated user from /auth/me', function () {
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);
});

it('logs out and revokes the bearer token', function () {
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
});
