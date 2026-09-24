<?php

namespace App\Modules\Mailer\Jobs;

use App\Modules\Mailer\Domain\Importer\ImportRunner;
use App\Modules\Mailer\Models\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RunImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 50;

    public function __construct(public string $trigger = ImportRun::TRIGGER_MANUAL)
    {
        $this->onQueue((string) config('mailer.queue', 'mailer'));
    }

    public function handle(ImportRunner $runner): void
    {
        $runner->run($this->trigger);
    }
}
