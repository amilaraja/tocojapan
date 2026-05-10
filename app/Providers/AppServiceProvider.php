<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Share the current user's favorited vehicle IDs across views so the
        // heart icon on every card can render its filled state without N queries.
        View::composer('*', function ($view) {
            $view->with('favoritedIds', Auth::check()
                ? Auth::user()->favorites()->pluck('vehicle_id')->all()
                : []);
        });
    }
}
