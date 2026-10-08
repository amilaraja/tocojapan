<?php

namespace App\Modules\Mailer\Jobs;

use App\Modules\Mailer\Domain\Buyers\BuyerBackfill;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * TOC-BUY-006 from the admin: reads messages, then fills Brevo, in slices
 * that fit the worker's 50 s limit, re-queuing itself until both are done.
 */
class RunBuyerBackfill implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 50;

    public function __construct()
    {
        $this->onQueue((string) config('mailer.queue', 'mailer'));
    }

    public function handle(BuyerBackfill $backfill): void
    {
        $deadline = microtime(true) + (int) config('mailer.import.run_seconds', 40);

        $read = $backfill->readMessages($deadline);
        // Only re-queue on progress, so a stuck message can never loop forever.
        if ($read > 0) {
            self::dispatch();

            return;
        }

        $synced = $backfill->syncBrevo($deadline);
        if ($synced['done'] > 0 && $backfill->status()['brevo_left'] > 0) {
            self::dispatch();
        }
    }
}
