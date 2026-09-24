<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Domain\Importer\Backfill;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BackfillCommand extends Command
{
    protected $signature = 'mailer:backfill {--from= : Start date YYYY-MM-DD} {--pause} {--resume} {--status}';

    protected $description = 'One-time import of older messages, in batches of 100 (resumable)';

    public function handle(Backfill $backfill): int
    {
        if ($this->option('pause')) {
            $backfill->pause();
        } elseif ($this->option('resume')) {
            $backfill->resume();
        } elseif ($from = $this->option('from')) {
            $backfill->start(CarbonImmutable::parse($from));
            $this->info("Backfill from {$from} queued.");
        } elseif (! $this->option('status')) {
            $this->error('Use --from=YYYY-MM-DD, --pause, --resume or --status.');

            return self::FAILURE;
        }

        $c = $backfill->cursor();
        $this->line($c ? sprintf('Status: %s, from %s, %d batches, %d messages', $c['status'], $c['from'], $c['batches_done'] ?? 0, $c['messages_seen'] ?? 0) : 'No backfill yet.');

        return self::SUCCESS;
    }
}
