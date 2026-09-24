<?php

namespace App\Modules\Mailer\Domain\Importer;

use App\Modules\Mailer\Jobs\RunBackfillBatch;
use Carbon\CarbonImmutable;

/** Start / pause / resume the one-time backfill (TOC-IMP-008). */
class Backfill
{
    public function __construct(protected ImportRunner $runner) {}

    public function start(CarbonImmutable $from): void
    {
        $this->runner->state()->forceFill(['backfill_cursor' => [
            'from' => $from->toDateString(),
            'page_token' => null,
            'batches_done' => 0,
            'messages_seen' => 0,
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
        ]])->save();

        RunBackfillBatch::dispatch();
    }

    public function pause(): void
    {
        $this->setStatus('paused');
    }

    public function resume(): void
    {
        if ($this->setStatus('running')) {
            RunBackfillBatch::dispatch();
        }
    }

    /** @return array<string, mixed>|null */
    public function cursor(): ?array
    {
        return $this->runner->state()->backfill_cursor;
    }

    protected function setStatus(string $status): bool
    {
        $state = $this->runner->state();
        $cursor = $state->backfill_cursor;
        if (! $cursor || ($cursor['status'] ?? null) === 'done') {
            return false;
        }
        $cursor['status'] = $status;
        $state->forceFill(['backfill_cursor' => $cursor])->save();

        return true;
    }
}
