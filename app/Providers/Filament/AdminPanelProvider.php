<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Auth\Pages\Login;
use App\Filament\Admin\Widgets\QuotesAndOrders;
use App\Filament\Admin\Widgets\SearchConsoleChart;
use App\Filament\Admin\Widgets\StatsOverview;
use App\Modules\Crm\CrmServiceProvider;
use App\Modules\Mailer\MailerServiceProvider;
use App\Modules\PartnerApi\PartnerApiServiceProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
    public function boot(): void
    {
        // Admin-side DateTimePicker default. Without this, Filament v5's picker
        // reads/writes in config('app.timezone') = UTC, so an admin typing
        // "09:17" intending JST stores 09:17 UTC and the row appears scheduled
        // for the future on the live site (bit us with stock E02020).
        FilamentTimezone::set('Asia/Tokyo');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->brandName('Toco Japan Admin')
            ->maxContentWidth(Width::Full)
            // Bell for partner network alerts (UI brief 6.1).
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->navigationGroups(['Catalogue', 'Content', 'Enquiries', 'Mailer', 'Partner Network', 'Shipping', 'System'])
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
                // Admin home; staff whose job is the CRM are sent on to /crm.
                \App\Modules\Crm\Filament\Pages\HomeDashboard::class,
                // TOCO Mailer module screens (each gates itself via canAccess()).
                ...MailerServiceProvider::filamentPages(),
                // Partner Stock API screens (gated by partners.admin).
                ...PartnerApiServiceProvider::filamentPages(),
            ])
            ->resources([...MailerServiceProvider::filamentResources(), ...PartnerApiServiceProvider::filamentResources()])
            // TOCO CRM moved to its own panel at /crm (CR-TOC-02-01).
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('Open CRM')
                    ->url('/crm')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->sort(-1)
                    ->visible(fn (): bool => CrmServiceProvider::enabled() && \App\Modules\Crm\Support\CrmAccess::canUse()),
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                AccountWidget::class,
                StatsOverview::class,
                SearchConsoleChart::class,
                QuotesAndOrders::class,
                ...PartnerApiServiceProvider::filamentWidgets(),
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
                // Filament's Authenticate, but CRM-only staff are sent to /crm instead of a 403.
                \App\Modules\Crm\Http\Middleware\SendCrmStaffToCrm::class,
            ]);
    }
}
