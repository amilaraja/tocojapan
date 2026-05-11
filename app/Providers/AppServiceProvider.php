<?php

namespace App\Providers;

use App\Listeners\ConvertVehiclePhotoOnUpload;
use App\Mail\Transport\GmailApiTransport;
use App\Services\CurrencyRates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
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

        View::composer('components.layouts.site', function ($view) {
            $rates = app(CurrencyRates::class);
            $view->with('currencyOptions', $rates->activeCurrencies());
            $view->with('currentCurrency', $rates->userCurrencyCode());

            $unread = 0;
            if (Auth::check()) {
                $unread = \App\Models\OrderMessage::query()
                    ->whereHas('order', fn ($q) => $q->where('user_id', Auth::id()))
                    ->where('from_admin', true)
                    ->whereNull('read_by_customer_at')
                    ->count();
            }
            $view->with('unreadMessageCount', $unread);
        });

        Event::listen(MediaHasBeenAddedEvent::class, ConvertVehiclePhotoOnUpload::class);

        Mail::extend('gmail-api', function () {
            return new GmailApiTransport(
                clientId: (string) config('services.gmail_oauth.client_id'),
                clientSecret: (string) config('services.gmail_oauth.client_secret'),
                refreshToken: (string) config('services.gmail_oauth.refresh_token'),
            );
        });

        Blade::directive('money', function (string $expr) {
            return "<?php echo app(\App\Services\CurrencyRates::class)->format((float) ({$expr}), app(\App\Services\CurrencyRates::class)->userCurrencyCode()); ?>";
        });
    }
}
