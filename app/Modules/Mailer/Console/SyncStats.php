<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Domain\Campaigns\StatsSync;
use Illuminate\Console\Command;

class SyncStats extends Command
{
    protected $signature = 'mailer:sync-stats';

    protected $description = 'Sync status and statistics from Brevo for campaigns pushed in the last 60 days';

    public function handle(StatsSync $sync): int
    {
        $this->line('Synced '.$sync->run().' campaigns.');

        return self::SUCCESS;
    }
}
