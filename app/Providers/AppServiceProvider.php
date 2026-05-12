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

        View::composer('*', function ($view) {
            // Resolve the visitor's preferred destination port (cookie-driven)
            // and share it + a list of all countries+ports so the picker can
            // render without repeated DB queries.
            $portId = (int) request()->cookie('toco_port');
            $destPort = null;
            if ($portId > 0) {
                $destPort = \App\Models\Port::query()
                    ->with('country')
                    ->where('id', $portId)
                    ->where('is_active', true)
                    ->first();
            }
            $view->with('destPort', $destPort);
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

        // @cif($vehicle, $port) → small inline CIF label for the visitor's
        // chosen currency. Returns nothing if vehicle has no m3 or no port.
        Blade::directive('cif', function (string $expr) {
            return "<?php
                [\$__cifVeh, \$__cifPort] = [{$expr}];
                if (\$__cifVeh && \$__cifPort && (float) \$__cifVeh->price_fob > 0 && (float) \$__cifVeh->m3 > 0) {
                    \$__cifBreak = app(\App\Services\CifCalculator::class)->calculate(
                        priceFob: (float) \$__cifVeh->price_fob,
                        m3: (float) \$__cifVeh->m3,
                        port: \$__cifPort,
                    );
                    echo app(\App\Services\CurrencyRates::class)->format((float) \$__cifBreak['cif_total'], app(\App\Services\CurrencyRates::class)->userCurrencyCode());
                }
            ?>";
        });
    }
}
