<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ExpoPushTokenController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public catalog (no auth) — Expo browses without sign-in.
    Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/{slug}', [VehicleController::class, 'show'])->name('vehicles.show');
    Route::get('makes', [CatalogController::class, 'makes'])->name('makes.index');
    Route::get('makes/{makeSlug}/models', [CatalogController::class, 'models'])->name('makes.models');
    Route::get('body-types', [CatalogController::class, 'bodyTypes'])->name('body-types.index');
    Route::get('countries', [CatalogController::class, 'countries'])->name('countries.index');

    // Auth (public).
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    // Authenticated.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::post('expo-push-tokens', [ExpoPushTokenController::class, 'store'])->name('expo-push-tokens.store');
        Route::delete('expo-push-tokens', [ExpoPushTokenController::class, 'destroy'])->name('expo-push-tokens.destroy');
    });
});
