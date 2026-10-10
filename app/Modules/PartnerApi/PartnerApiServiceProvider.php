<?php

namespace App\Modules\PartnerApi;

use App\Models\Vehicle;
use App\Modules\PartnerApi\Domain\Images\ImageVariants;
use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Http\Middleware\EnforceLimits;
use App\Modules\PartnerApi\Http\Middleware\PartnerBoundary;
use App\Modules\PartnerApi\Http\Middleware\RequireScope;
use App\Modules\PartnerApi\Models\PartnerAccessToken;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use App\Modules\PartnerApi\Support\PartnerApi;
use App\Modules\PartnerApi\Support\Problem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * TOCO Partner Stock API (SRS-TOC-PAPI). Isolated under app/Modules/PartnerApi;
 * hooks into shared code are listed in docs/partner-api/integration-notes.md.
 */
class PartnerApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/partner_api.php', 'partner_api');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('partner.auth', AuthenticatePartner::class);
        $router->aliasMiddleware('partner.scope', RequireScope::class);
        $router->aliasMiddleware('partner.limits', EnforceLimits::class);

        $this->registerRoutes();

        // Every error on a partner route is problem+json (SRS §6 conventions).
        $handler = $this->app->make(ExceptionHandler::class);
        if ($handler instanceof Handler) {
            $handler->renderable(function (Throwable $e, Request $request) {
                return PartnerApi::isPartnerRequest($request) ? Problem::fromThrowable($e, $request) : null;
            });
        }

        // Photo added, removed or reordered: the vehicle counts as changed, so
        // partners' change syncs pick it up (FR-SYNC-01).
        $touchVehicle = function (Media $media): void {
            if ($media->model_type === (new Vehicle)->getMorphClass() && ($media->wasRecentlyCreated || $media->wasChanged('order_column') || ! $media->exists)) {
                Vehicle::withTrashed()->whereKey($media->model_id)->first()?->touch();
            }
        };
        Media::saved($touchVehicle);
        Media::deleted($touchVehicle);
        Media::deleted(fn (Media $media) => ImageVariants::forget($media->id));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->call(fn () => PartnerAccessToken::where('expires_at', '<', now()->subDay())->delete())
                ->daily()->name('partner-api:prune-tokens');
            // §8.6: request logs kept 12 months.
            $schedule->call(fn () => PartnerRequestLog::where('created_at', '<', now()->subMonths(12))->delete())
                ->dailyAt('04:20')->name('partner-api:prune-request-logs');
        });
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $routes = Route::middleware(PartnerBoundary::class)
            ->prefix(config('partner_api.prefix'))
            ->name('partner.');

        if ($host = config('partner_api.host')) {
            $routes->domain($host);
        }

        $routes->group(__DIR__.'/routes/partner.php');
    }
}
