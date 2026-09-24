<?php

namespace App\Modules\Mailer\Jobs;

use App\Modules\Mailer\Domain\Importer\ImportRunner;
use App\Modules\Mailer\Models\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** One batch of up to 100 messages; queues the next batch until done or paused (TOC-IMP-008). */
class RunBackfillBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 20;

    public int $timeout = 50;

    public function __construct()
    {
        $this->onQueue((string) config('mailer.queue', 'mailer'));
    }

    public function handle(ImportRunner $runner): void
    {
        $run = $runner->backfillBatch();
        $cursor = $runner->state()->backfill_cursor ?? [];

        if (($cursor['status'] ?? null) !== 'running') {
            return; // done or paused
        }

        // null = the normal importer held the lock; failed = try the same batch again.
        if ($run === null || $run->status === ImportRun::STATUS_FAILED) {
            $this->release(60);

            return;
        }

        static::dispatch();
    }
}
