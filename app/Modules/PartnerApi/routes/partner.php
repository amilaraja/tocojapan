<?php

use App\Modules\PartnerApi\Http\Controllers\MeController;
use App\Modules\PartnerApi\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Route;

// Loaded by PartnerApiServiceProvider inside the partner prefix (and host,
// when set) with the PartnerBoundary middleware. Every route here must be
// documented in docs/openapi.yaml (tests/PartnerApi/Feature/DocsCoverageTest).

Route::post('oauth/token', TokenController::class)->name('token');

Route::middleware('partner.auth')->group(function () {
    Route::get('me', MeController::class)->name('me');
});
