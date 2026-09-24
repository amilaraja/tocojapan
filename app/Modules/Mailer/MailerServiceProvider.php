<?php

namespace App\Modules\Mailer;

use App\Modules\Mailer\Console\BackfillCommand;
use App\Modules\Mailer\Console\BrevoCheck;
use App\Modules\Mailer\Console\BrevoSaveTemplate;
use App\Modules\Mailer\Console\BrevoSetup;
use App\Modules\Mailer\Console\Cleanup;
use App\Modules\Mailer\Console\Import;
use App\Modules\Mailer\Console\RenderSample;
use App\Modules\Mailer\Console\SyncStats;
use App\Modules\Mailer\Domain\Importer\GmailReader;
use App\Modules\Mailer\Domain\Importer\MailboxReader;
use App\Modules\Mailer\Filament\Clusters\Importer;
use App\Modules\Mailer\Filament\Pages\Importer\BackfillPage;
use App\Modules\Mailer\Filament\Pages\Importer\ImporterStatus;
use App\Modules\Mailer\Filament\Pages\Importer\RuleTester;
use App\Modules\Mailer\Filament\Pages\MailerSettingsPage;
use App\Modules\Mailer\Filament\Resources\ApprovedSenders\ApprovedSenderResource;
use App\Modules\Mailer\Filament\Resources\Banners\BannerResource;
use App\Modules\Mailer\Filament\Resources\Campaigns\CampaignResource;
use App\Modules\Mailer\Filament\Resources\ContactImports\ContactImportResource;
use App\Modules\Mailer\Filament\Resources\IgnoreRules\IgnoreRuleResource;
use App\Modules\Mailer\Filament\Resources\ImportRuns\ImportRunResource;
use App\Modules\Mailer\Filament\Pages\Overview;
use App\Modules\Mailer\Filament\Widgets\ImportStats;
use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
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
        $this->app->bind(MailboxReader::class, GmailReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mailer');
        Blade::anonymousComponentPath(__DIR__.'/resources/views/email/components', 'mailer-email');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RenderSample::class,
                SyncStats::class,
                Import::class,
                BackfillCommand::class,
                Cleanup::class,
                BrevoCheck::class,
                BrevoSetup::class,
                BrevoSaveTemplate::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Checks every minute; runs only when the configured interval has passed.
            $schedule->command('mailer:import')->everyMinute()->withoutOverlapping(20)->runInBackground();
            $schedule->command('mailer:cleanup')->dailyAt('04:10');
            $schedule->command('mailer:sync-stats')->hourly()->withoutOverlapping(30);
        });

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
            Importer::class,
            ImporterStatus::class,
            BackfillPage::class,
            RuleTester::class,
            MailerSettingsPage::class,
        ];
    }

    /** @return list<class-string> */
    public static function filamentResources(): array
    {
        return [
            CampaignResource::class,
            BannerResource::class,
            ApprovedSenderResource::class,
            IgnoreRuleResource::class,
            ImportRunResource::class,
            ContactImportResource::class,
        ];
    }
}
