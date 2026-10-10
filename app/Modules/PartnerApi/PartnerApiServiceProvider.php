<?php

namespace App\Modules\PartnerApi;

use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Http\Middleware\PartnerBoundary;
use App\Modules\PartnerApi\Http\Middleware\RequireScope;
use App\Modules\PartnerApi\Models\PartnerAccessToken;
use App\Modules\PartnerApi\Support\PartnerApi;
use App\Modules\PartnerApi\Support\Problem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
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

        $this->registerRoutes();

        // Every error on a partner route is problem+json (SRS §6 conventions).
        $handler = $this->app->make(ExceptionHandler::class);
        if ($handler instanceof Handler) {
            $handler->renderable(function (Throwable $e, Request $request) {
                return PartnerApi::isPartnerRequest($request) ? Problem::fromThrowable($e, $request) : null;
            });
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->call(fn () => PartnerAccessToken::where('expires_at', '<', now()->subDay())->delete())
                ->daily()->name('partner-api:prune-tokens');
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
