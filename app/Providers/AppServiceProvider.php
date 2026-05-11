<?php

namespace App\Providers;

use App\Listeners\ConvertVehiclePhotoOnUpload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

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

        Event::listen(MediaHasBeenAddedEvent::class, ConvertVehiclePhotoOnUpload::class);
    }
}
