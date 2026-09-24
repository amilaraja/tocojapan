<?php

namespace App\Modules\Mailer\Filament\Widgets;

use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Models\ImportRun;
use App\Modules\Mailer\Support\MailerAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/** TOC-GEN-006: last import and contacts imported today / 7 / 30 days. */
class ImportStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return MailerAccess::canUse();
    }

    protected function getStats(): array
    {
        $tz = (string) config('mailer.display_timezone', 'Asia/Tokyo');

        $last = ImportRun::query()
            ->where('status', '!=', ImportRun::STATUS_SKIPPED)
            ->latest('started_at')
            ->first();

        $failed = $last?->status === ImportRun::STATUS_FAILED;

        $lastStat = Stat::make('Last import', $last ? $last->started_at->timezone($tz)->format('j M, H:i') : 'Not run yet')
            ->description(match (true) {
                $last === null => 'The inbox importer has not run yet',
                $failed => 'Last import failed. Check the run log.',
                $last->status === ImportRun::STATUS_RUNNING => 'Running now',
                default => 'Finished without problems',
            })
            ->descriptionIcon($failed ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
            ->color($failed ? 'danger' : ($last ? 'success' : 'gray'));

        // "Today" is the Tokyo calendar day; 7 and 30 days are rolling windows.
        return [
            $lastStat,
            Stat::make('Contacts imported today', number_format($this->importedSince(now($tz)->startOfDay()))),
            Stat::make('Last 7 days', number_format($this->importedSince(now()->subDays(7)))),
            Stat::make('Last 30 days', number_format($this->importedSince(now()->subDays(30)))),
        ];
    }

    protected function importedSince(Carbon $since): int
    {
        return ContactImport::query()
            ->whereIn('outcome', [ContactImport::OUTCOME_ADDED, ContactImport::OUTCOME_UPDATED])
            ->where('created_at', '>=', $since->copy()->utc())
            ->count();
    }
}
