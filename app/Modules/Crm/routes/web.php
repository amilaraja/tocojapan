<?php

use App\Modules\Crm\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

// Old admin addresses of the CRM (bookmarks, earlier staff emails) → /crm (CR-TOC-02-01).
Route::get('/admin/crm/{path?}', fn (?string $path = null) => redirect('/crm'.($path ? '/'.$path : '').(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301))
    ->where('path', '.*')->name('crm.legacy-admin');

// Customer view (SRS §9). The list needs a login; each Case opens by its
// secret link, so guests can follow it from their emails (CRM-POR-006).
Route::middleware(['auth', 'verified'])->get('/my-purchases', [PortalController::class, 'index'])->name('crm.portal.index');

Route::prefix('track/{case}/{token}')->name('crm.portal.')->where(['case' => 'C-\d{4}-\d{5}', 'token' => '[A-Za-z0-9]{48}'])->group(function () {
    Route::get('/', [PortalController::class, 'show'])->name('show');
    Route::post('/confirm/{step}', [PortalController::class, 'confirm'])->whereIn('step', ['order_confirmed', 'documents_received', 'car_received'])->name('confirm');
    Route::post('/message', [PortalController::class, 'message'])->middleware('throttle:10,1')->name('message');
    Route::post('/payment-slip', [PortalController::class, 'paymentSlip'])->middleware('throttle:10,1')->name('slip');
    Route::get('/documents/{media}', [PortalController::class, 'document'])->whereNumber('media')->name('document');
});
