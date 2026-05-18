<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Auth\Pages\Login;
use App\Filament\Admin\Widgets\QuotesAndOrders;
use App\Filament\Admin\Widgets\SearchConsoleChart;
use App\Filament\Admin\Widgets\StatsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->brandName('Toco Japan Admin')
            ->maxContentWidth(Width::Full)
            ->navigationGroups(['Catalogue', 'Content', 'Enquiries', 'Shipping', 'System'])
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('@include("filament.admin.auth.head")'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): string => Blade::render('@include("filament.admin.auth.turnstile")'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn (): string => Blade::render('@include("filament.admin.auth.brand")'))
            ->colors([
                'primary' => Color::hex('#E30613'),
                'gray' => Color::Slate,
                'info' => Color::hex('#1F2356'),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                AccountWidget::class,
                StatsOverview::class,
                SearchConsoleChart::class,
                QuotesAndOrders::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
