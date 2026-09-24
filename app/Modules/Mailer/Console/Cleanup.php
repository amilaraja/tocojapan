<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Models\ImportRun;
use Illuminate\Console\Command;

/**
 * TOC-LOG-004: run logs and audit records older than 12 months are deleted.
 * Processed message ids are kept: they guarantee "at most once, ever".
 */
class Cleanup extends Command
{
    protected $signature = 'mailer:cleanup';

    protected $description = 'Delete Mailer run logs and audit records older than 12 months';

    public function handle(): int
    {
        $cutoff = now()->subMonths((int) config('mailer.retention_months', 12));

        $audit = ContactImport::query()->where('created_at', '<', $cutoff)->delete();
        $runs = ImportRun::query()->where('started_at', '<', $cutoff)->delete();

        $this->line("Deleted {$audit} audit records and {$runs} runs older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
