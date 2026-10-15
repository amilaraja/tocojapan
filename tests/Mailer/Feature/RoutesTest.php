<?php

use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// TOC-NFR-002: every Mailer route is an admin route behind Filament auth.
// The only public Mailer files are images under /storage/email-assets,
// served by the existing storage link, not by a route.
it('puts every Mailer route under /admin with the admin auth middleware', function () {
    $mailerRoutes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'mailer')
        || str_contains((string) $route->getActionName(), 'Modules\\Mailer'));

    expect($mailerRoutes)->not->toBeEmpty();

    foreach ($mailerRoutes as $route) {
        expect($route->uri())->toStartWith('admin/mailer')
            // The admin panel uses a subclass of Filament's Authenticate (CRM staff → /crm, CR-TOC-02-01).
            ->and(collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && is_a($m, Authenticate::class, true)))->toBeTrue();
    }
});
