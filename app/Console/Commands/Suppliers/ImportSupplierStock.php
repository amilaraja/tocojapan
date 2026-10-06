<?php

namespace App\Console\Commands\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierImport;
use App\Suppliers\SupplierImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportSupplierStock extends Command
{
    protected $signature = 'suppliers:import
        {supplier : Supplier slug, e.g. oneprice}
        {file : Path to the supplier stock file}
        {--partial : Add/update only — never delist vehicles missing from the file}
        {--apply : Apply straight after the preview (otherwise approve it in the admin)}
        {--force : With --apply, also apply when the delist guard trips}
        {--margin-percent= : Profit margin % for this import (default: supplier / site setting)}
        {--margin-fixed= : Fixed profit per vehicle in USD for this import (default: supplier / site setting)}';

    protected $description = 'Import a supplier stock file (same staged sync as the admin "Stock imports" screen).';

    public function handle(SupplierImporter $importer): int
    {
        $supplier = Supplier::query()->where('slug', $this->argument('supplier'))->first();
        if (! $supplier) {
            $this->error('Unknown supplier.');

            return self::FAILURE;
        }
        $source = (string) $this->argument('file');
        if (! is_file($source)) {
            $this->error("File not found: {$source}");

            return self::FAILURE;
        }

        // Keep a copy next to admin uploads so the run is auditable.
        $stored = 'supplier-imports/'.$supplier->slug.'/'.now()->format('Ymd-His').'-'.basename($source);
        Storage::disk('local')->put($stored, fopen($source, 'rb'));

        $import = SupplierImport::query()->create([
            'supplier_id' => $supplier->id,
            'status' => SupplierImport::STATUS_QUEUED,
            'mode' => $this->option('partial') ? 'partial' : 'full',
            'original_name' => basename($source),
            'file_path' => Storage::disk('local')->path($stored),
            'stats' => [
                'auto_apply' => (bool) $this->option('apply'),
                'force' => (bool) $this->option('force'),
                'margin_override' => array_filter([
                    'percent' => $this->option('margin-percent'),
                    'fixed_usd' => $this->option('margin-fixed'),
                ], fn ($v) => $v !== null && $v !== '') ?: null,
            ],
        ]);

        $this->info("Import #{$import->id} ({$import->mode}) for {$supplier->name}…");
        $last = '';
        while (true) {
            $import = $importer->run($import, microtime(true) + 5);
            $line = sprintf('%-15s rows=%d valid=%d errors=%d processed=%d', $import->statusLabel(), $import->total_rows, $import->valid_rows, $import->error_rows, $import->processed_rows);
            if ($line !== $last) {
                $this->line($line);
                $last = $line;
            }
            if (! $import->isRunning()) {
                break;
            }
        }

        $this->newLine();
        $rows = [];
        foreach ((array) $import->stat('counts', []) as $k => $v) {
            $rows[] = ["preview: {$k}", $v];
        }
        foreach ((array) $import->stat('applied', []) as $k => $v) {
            $rows[] = ["applied: {$k}", $v];
        }
        $rows[] = ['to delist', $import->stat('to_delist')];
        $rows[] = ['live before', $import->stat('live_before')];
        $rows[] = ['live after', $import->stat('live_after', '-')];
        $rows[] = ['makes created', $import->stat('makes_created')];
        $rows[] = ['models created', $import->stat('models_created')];
        $this->table(['', 'count'], $rows);

        if ($import->status === SupplierImport::STATUS_PREVIEWED) {
            $this->warn($import->needsDelistConfirmation()
                ? 'Delist guard tripped — review and approve in Admin → Stock imports, or rerun with --apply --force.'
                : 'Preview ready — approve it in Admin → Stock imports, or rerun with --apply.');
        }
        if ($import->status === SupplierImport::STATUS_FAILED) {
            $this->error($import->message ?? 'Import failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
