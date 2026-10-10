<?php

use App\Modules\PartnerApi\Http\Controllers\CatalogController;
use App\Modules\PartnerApi\Http\Controllers\CifController;
use App\Modules\PartnerApi\Http\Controllers\ImageController;
use App\Modules\PartnerApi\Http\Controllers\MeController;
use App\Modules\PartnerApi\Http\Controllers\TokenController;
use App\Modules\PartnerApi\Http\Controllers\UsageController;
use App\Modules\PartnerApi\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

// Loaded by PartnerApiServiceProvider inside the partner prefix (and host,
// when set) with the PartnerBoundary middleware. Every route here must be
// documented in docs/openapi.json (tests/PartnerApi/Feature/DocsCoverageTest).

Route::post('oauth/token', TokenController::class)->name('token');

// The signed token is the credential (§9.2); no bearer token here.
Route::get('img/{token}', ImageController::class)->where('token', '[A-Za-z0-9_\-.]+')->name('image');

Route::middleware(['partner.auth', 'partner.limits'])->group(function () {
    Route::get('me', MeController::class)->name('me');

    Route::middleware('partner.scope:stock:read')->group(function () {
        Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicles/{stock_no}', [VehicleController::class, 'show'])->name('vehicles.show');

        Route::get('catalog/makes', [CatalogController::class, 'makes'])->name('catalog.makes');
        Route::get('catalog/models', [CatalogController::class, 'models'])->name('catalog.models');
        Route::get('catalog/body-types', [CatalogController::class, 'bodyTypes'])->name('catalog.body-types');
        Route::get('catalog/countries', [CatalogController::class, 'countries'])->name('catalog.countries');
        Route::get('catalog/ports', [CatalogController::class, 'ports'])->name('catalog.ports');
    });

    Route::post('cif', CifController::class)->middleware('partner.scope:cif:read')->name('cif');
    Route::get('usage', UsageController::class)->middleware('partner.scope:usage:read')->name('usage');
});
