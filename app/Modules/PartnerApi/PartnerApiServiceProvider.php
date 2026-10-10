<?php

namespace App\Modules\PartnerApi;

use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Console\SendWebhooks;
use App\Modules\PartnerApi\Domain\Webhooks\WebhookOutbox;
use App\Modules\PartnerApi\Domain\Images\ImageVariants;
use App\Modules\PartnerApi\Filament\Pages\PartnerUsage;
use App\Modules\PartnerApi\Filament\Resources\Holds\VehicleHoldResource;
use App\Modules\PartnerApi\Filament\Resources\Partners\PartnerResource;
use App\Modules\PartnerApi\Http\Controllers\DocsController;
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
        $this->loadViewsFrom(__DIR__.'/resources/views', 'partner-api');

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

        // Change notifications (FR-SYNC-02). Never allowed to break a save.
        $notify = function (callable $callback): void {
            try {
                $callback();
            } catch (Throwable $e) {
                report($e);
            }
        };
        Vehicle::created(fn (Vehicle $vehicle) => $notify(fn () => WebhookOutbox::vehicleChanged($vehicle, created: true)));
        Vehicle::updated(fn (Vehicle $vehicle) => $notify(fn () => WebhookOutbox::vehicleChanged($vehicle)));
        Vehicle::deleted(fn (Vehicle $vehicle) => $notify(fn () => WebhookOutbox::vehicleChanged($vehicle, deleted: true)));
        VehicleHold::updated(fn (VehicleHold $hold) => $notify(function () use ($hold) {
            if ($hold->partner_id && $hold->wasChanged('state')) {
                match (true) {
                    $hold->state === VehicleHold::STATE_EXPIRED => WebhookOutbox::holdEvent($hold, WebhookOutbox::HOLD_EXPIRED),
                    $hold->state === VehicleHold::STATE_RELEASED && ! str_starts_with((string) $hold->ended_reason, 'Released by partner')
                        => WebhookOutbox::holdEvent($hold, WebhookOutbox::HOLD_RELEASED),
                    default => null,
                };
            }
        }));

        if ($this->app->runningInConsole()) {
            $this->commands([SendWebhooks::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('partners:webhooks')->everyMinute()->withoutOverlapping(5);
            $schedule->call(fn () => PartnerAccessToken::where('expires_at', '<', now()->subDay())->delete())
                ->daily()->name('partner-api:prune-tokens');
            // §8.6: request logs kept 12 months.
            $schedule->call(fn () => PartnerRequestLog::where('created_at', '<', now()->subMonths(12))->delete())
                ->dailyAt('04:20')->name('partner-api:prune-request-logs');
        });
    }

    /**
     * Admin pages registered on the panel by AdminPanelProvider. Each gates
     * itself with PartnerAccess::canAdmin().
     *
     * @return list<class-string>
     */
    public static function filamentPages(): array
    {
        return [PartnerUsage::class];
    }

    /** @return list<class-string> */
    public static function filamentResources(): array
    {
        return [PartnerResource::class, VehicleHoldResource::class];
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

        // Partner developer docs on the main site (FR-DOC-01), outside the API prefix.
        Route::middleware('web')->group(function () {
            Route::get('partner/docs', [DocsController::class, 'guide'])->name('partner-docs');
            Route::get('partner/docs/openapi.json', [DocsController::class, 'openapi'])->name('partner-docs.openapi');
        });
    }
}
