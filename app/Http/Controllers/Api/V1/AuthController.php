<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $deviceName = $data['device_name'] ?? 'expo-app';

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'country_id' => $data['country_id'] ?? null,
        ]);

        $user->assignRole('customer');
        $user->load('country');

        $token = $user->createToken($deviceName)->plainTextToken;

        return ApiResponse::created([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $deviceName = $data['device_name'] ?? 'expo-app';

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('The provided credentials are incorrect.', 422, [
                'email' => ['Invalid email or password.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $user->load('country');

        $token = $user->createToken($deviceName)->plainTextToken;

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('country');

        return ApiResponse::ok([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::ok(['message' => 'Logged out.']);
    }
}
