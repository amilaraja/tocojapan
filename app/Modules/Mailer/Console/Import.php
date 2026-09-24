<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Domain\Importer\ImportRunner;
use App\Modules\Mailer\Domain\Importer\MailboxReader;
use App\Modules\Mailer\Models\ImportRun;
use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Console\Command;

/**
 * Scheduled every minute; runs only when the configured interval has passed
 * since the last scheduled run (TOC-IMP-002). --now runs immediately.
 */
class Import extends Command
{
    protected $signature = 'mailer:import {--now : Run immediately (manual run)}';

    protected $description = 'Import buyer addresses from the mailbox into Brevo';

    public function handle(ImportRunner $runner, MailboxReader $reader, MailerSettings $settings): int
    {
        if (! $this->option('now')) {
            if (! $reader->isConfigured()) {
                return self::SUCCESS; // not set up yet: stay quiet, no failure alerts
            }

            $interval = (int) $settings->get('import_interval_minutes', 15);
            $last = ImportRun::query()->where('trigger', ImportRun::TRIGGER_SCHEDULE)
                ->where('status', '!=', ImportRun::STATUS_SKIPPED)
                ->latest('started_at')->value('started_at');

            // 30 s tolerance so a 15-minute interval does not slip to 16.
            if ($last && now()->diffInSeconds($last, true) < $interval * 60 - 30) {
                return self::SUCCESS;
            }
        }

        $run = $runner->run($this->option('now') ? ImportRun::TRIGGER_MANUAL : ImportRun::TRIGGER_SCHEDULE);

        $this->line(sprintf('Run #%d %s: %d scanned, %d added, %d updated, %d failed%s',
            $run->id, $run->status, $run->scanned, $run->created, $run->updated, $run->failed,
            $run->error ? ' ('.$run->error.')' : ''));

        return $run->status === ImportRun::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }
}
