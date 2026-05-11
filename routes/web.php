<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CifController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VehicleController::class, 'home'])->name('home');

Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
Route::get('/vehicles/{slug}', [VehicleController::class, 'show'])->name('vehicles.show');

Route::get('/cif', [CifController::class, 'index'])->name('cif.index');

Route::post('/currency/{code}', [CurrencyController::class, 'set'])->name('currency.set');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{slug}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::post('/vehicles/{slug}/quote', [QuoteController::class, 'store'])->name('quotes.store');
    Route::post('/quotes/{quote}/messages', [QuoteController::class, 'reply'])->name('quotes.reply');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/messages', [OrderController::class, 'postMessage'])->name('orders.messages.store');

    Route::post('/checkout/{slug}', [CheckoutController::class, 'start'])->name('checkout.start');
    Route::get('/checkout/{order}/return', [CheckoutController::class, 'return'])->name('checkout.return');
    Route::get('/checkout/{order}/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
});

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

require __DIR__.'/auth.php';

// CMS catch-all — must come last so it doesn't shadow more specific routes.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?')
    ->name('cms.page');
