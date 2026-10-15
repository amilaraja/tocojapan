<?php

namespace App\Modules\Crm\Filament;

use App\Filament\Admin\Auth\Pages\Login;
use App\Modules\Crm\CrmServiceProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The sales team's own workspace at /crm (CR-TOC-02-01). Same users, session and
 * login as /admin; CRM-only staff can open this panel only (User::canAccessPanel).
 */
class CrmPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('crm')
            ->path('crm')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->brandName('TOCO CRM')
            ->maxContentWidth(Width::Full)
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('@include("filament.admin.auth.head")'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): string => Blade::render('@include("filament.admin.auth.turnstile")'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn (): string => Blade::render('@include("filament.admin.auth.brand", ["heading" => "CRM sign in", "subheading" => "TOCO CRM sales workspace"])'))
            ->colors([
                'primary' => Color::hex('#E30613'),
                'gray' => Color::Slate,
                'info' => Color::hex('#1F2356'),
            ])
            ->pages(CrmServiceProvider::filamentPages())
            ->resources(CrmServiceProvider::filamentResources())
            ->navigationItems([
                // Site admins use both panels with one sign-in.
                NavigationItem::make('Website admin')
                    ->url('/admin')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->sort(100)
                    ->visible(fn (): bool => (bool) auth()->user()?->hasAnyRole(['super_admin', 'admin'])),
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
