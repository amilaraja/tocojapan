<?php

namespace App\Modules\Mailer\Filament\Pages\Importer;

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Domain\Importer\MailboxReader;
use App\Modules\Mailer\Filament\Clusters\Importer;
use App\Modules\Mailer\Filament\Pages\MailerSettingsPage;
use App\Modules\Mailer\Filament\Widgets\ImportStats;
use App\Modules\Mailer\Jobs\RunImport;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\ImportRun;
use App\Modules\Mailer\Models\ImportState;
use App\Modules\Mailer\Support\MailerAccess;
use App\Modules\Mailer\Support\MailerSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** S9 Importer overview: status, next/last run, Run now (TOC-IMP-002, 003). */
class ImporterStatus extends Page
{
    protected string $view = 'mailer::filament.importer-status';

    protected static ?string $cluster = Importer::class;

    protected static ?string $slug = 'status';

    protected static ?string $title = 'Inbox importer';

    protected static ?string $navigationLabel = 'Status';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }

    protected function getHeaderWidgets(): array
    {
        return [ImportStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runNow')
                ->label('Run now')
                ->icon(Heroicon::OutlinedPlay)
                ->action(function () {
                    abort_unless(static::canAccess(), 403);
                    RunImport::dispatch(ImportRun::TRIGGER_MANUAL);
                    Notification::make()->title('Import started. It appears in the run log within a minute.')->success()->send();
                }),
            Action::make('interval')
                ->label('Change interval')
                ->color('gray')
                ->url(MailerSettingsPage::getUrl()),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $settings = app(MailerSettings::class);
        $interval = (int) $settings->get('import_interval_minutes', 15);
        $tz = (string) config('mailer.display_timezone');
        $last = ImportRun::query()->where('status', '!=', ImportRun::STATUS_SKIPPED)->latest('started_at')->first();
        $lastScheduled = ImportRun::query()->where('trigger', ImportRun::TRIGGER_SCHEDULE)->where('status', '!=', ImportRun::STATUS_SKIPPED)->latest('started_at')->value('started_at');
        $state = ImportState::query()->first();
        $mailboxReady = app(MailboxReader::class)->isConfigured();

        return [
            'mailbox' => $settings->get('mailbox'),
            'mailboxReady' => $mailboxReady,
            'brevoReady' => app(BrevoClient::class)->isConfigured(),
            'activeSenders' => ApprovedSender::query()->where('active', true)->count(),
            'interval' => $interval,
            'last' => $last,
            'next' => $mailboxReady ? ($lastScheduled ? $lastScheduled->copy()->addMinutes($interval) : now())->timezone($tz) : null,
            'failures' => (int) ($state?->consecutive_failures ?? 0),
            'running' => $state?->lock_until?->isFuture() ?? false,
            'tz' => $tz,
        ];
    }
}
