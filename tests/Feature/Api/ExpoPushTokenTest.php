<?php

use App\Models\ExpoPushToken;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('upserts an expo push token for the authenticated user', function () {
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/expo-push-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'ios',
            'device_name' => 'iPhone 15',
            'app_version' => '1.0.0',
        ])
        ->assertOk()
        ->assertJsonPath('data.token', 'ExponentPushToken[abc123]');

    expect(ExpoPushToken::where('token', 'ExponentPushToken[abc123]')->where('user_id', $user->id)->exists())
        ->toBeTrue();

    // Re-posting same token updates rather than duplicates.
    $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/expo-push-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'ios',
            'app_version' => '1.0.1',
        ])
        ->assertOk();

    expect(ExpoPushToken::where('user_id', $user->id)->count())->toBe(1);
});

it('deletes an expo push token', function () {
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;
    $user->expoPushTokens()->create(['token' => 'ExponentPushToken[xyz]']);

    $this->withHeader('Authorization', "Bearer $token")
        ->deleteJson('/api/v1/expo-push-tokens', ['token' => 'ExponentPushToken[xyz]'])
        ->assertOk();

    expect(ExpoPushToken::where('user_id', $user->id)->count())->toBe(0);
});
