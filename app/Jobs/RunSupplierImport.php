<?php

namespace App\Jobs;

use App\Models\SupplierImport;
use App\Suppliers\SupplierImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Carries a supplier import forward in ~35 s slices (the scheduler-run
 * worker on this host has a 50 s job timeout) and re-queues itself until
 * the import needs approval or is finished.
 */
class RunSupplierImport implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 50;

    public int $tries = 3;

    public const SLICE_SECONDS = 35;

    public function __construct(public int $importId) {}

    public function uniqueId(): string
    {
        return (string) $this->importId;
    }

    public function handle(SupplierImporter $importer): void
    {
        $import = SupplierImport::query()->find($this->importId);
        if (! $import || ! $import->isRunning()) {
            return;
        }

        $import = $importer->run($import, microtime(true) + self::SLICE_SECONDS);

        if ($import->isRunning()) {
            self::dispatch($this->importId);
        }
    }
}
