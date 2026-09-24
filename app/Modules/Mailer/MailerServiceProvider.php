<?php

namespace App\Modules\Mailer;

use App\Modules\Mailer\Filament\Pages\MailerSettingsPage;
use App\Modules\Mailer\Filament\Pages\Overview;
use App\Modules\Mailer\Filament\Widgets\ImportStats;
use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * TOCO Mailer module (SRS-TOC-01). Isolated under app/Modules/Mailer; the
 * only hooks into shared code are listed in docs/mailer/integration-notes.md.
 */
class MailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/mailer.php', 'mailer');

        $this->app->scoped(MailerSettings::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mailer');

        // Widgets rendered inside Mailer pages (not on the dashboard) need an
        // explicit Livewire alias so follow-up requests can resolve them.
        Livewire::component('mailer.import-stats', ImportStats::class);
    }

    /**
     * Admin pages registered on the panel by AdminPanelProvider (menu
     * registration, TOC-GEN-004). Each page gates itself via canAccess().
     *
     * @return list<class-string>
     */
    public static function filamentPages(): array
    {
        return [
            Overview::class,
            MailerSettingsPage::class,
        ];
    }

    /** @return list<class-string> */
    public static function filamentResources(): array
    {
        return [];
    }
}
