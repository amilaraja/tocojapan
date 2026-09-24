<?php

namespace App\Modules\Mailer\Filament\Pages\Importer;

use App\Modules\Mailer\Domain\Importer\Backfill;
use App\Modules\Mailer\Filament\Clusters\Importer;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** S13 Backfill (TOC-IMP-008): start date, progress, pause and resume. */
class BackfillPage extends Page
{
    protected string $view = 'mailer::filament.backfill';

    protected static ?string $cluster = Importer::class;

    protected static ?string $slug = 'backfill';

    protected static ?string $title = 'Import older messages';

    protected static ?string $navigationLabel = 'Backfill';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }

    protected function getHeaderActions(): array
    {
        $cursor = app(Backfill::class)->cursor();
        $status = $cursor['status'] ?? null;

        return [
            Action::make('start')
                ->label($status ? 'Start again' : 'Start backfill')
                ->icon(Heroicon::OutlinedPlay)
                ->visible($status !== 'running')
                ->schema([
                    DatePicker::make('from')->label('Import messages received since')->required()->maxDate(now())->native(false),
                ])
                ->modalDescription('Messages already imported are skipped. Runs in the background in batches of 100.')
                ->action(function (array $data) {
                    abort_unless(static::canAccess(), 403);
                    app(Backfill::class)->start(CarbonImmutable::parse($data['from']));
                    Notification::make()->title('Backfill started.')->success()->send();
                }),
            Action::make('pause')
                ->label('Pause')->color('gray')->icon(Heroicon::OutlinedPause)
                ->visible($status === 'running')
                ->action(fn () => app(Backfill::class)->pause()),
            Action::make('resume')
                ->label('Resume')->icon(Heroicon::OutlinedPlay)
                ->visible($status === 'paused')
                ->action(fn () => app(Backfill::class)->resume()),
        ];
    }

    protected function getViewData(): array
    {
        return ['cursor' => app(Backfill::class)->cursor()];
    }
}
