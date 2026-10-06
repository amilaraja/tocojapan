<?php

namespace App\Console\Commands\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeDelistedSupplierStock extends Command
{
    protected $signature = 'suppliers:purge {--dry-run}';

    protected $description = 'Remove long-delisted supplier vehicles nobody ordered, quoted or saved, and old import working data.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        foreach (Supplier::query()->where('is_own_stock', false)->get() as $supplier) {
            $days = (int) $supplier->setting('purge_delisted_after_days');
            if ($days <= 0) {
                continue;
            }
            $query = DB::table('vehicles as v')
                ->where('v.supplier_id', $supplier->id)
                ->where('v.status', 'delisted')
                ->where('v.delisted_at', '<', now()->subDays($days))
                // Anything a customer interacted with stays (order history, quotes, favourites).
                ->whereNotExists(fn ($q) => $q->from('orders')->whereColumn('orders.vehicle_id', 'v.id'))
                ->whereNotExists(fn ($q) => $q->from('quotes')->whereColumn('quotes.vehicle_id', 'v.id'))
                ->whereNotExists(fn ($q) => $q->from('favorites')->whereColumn('favorites.vehicle_id', 'v.id'));

            $ids = $query->pluck('v.id');
            $this->info("{$supplier->name}: {$ids->count()} delisted vehicle(s) older than {$days} days".($dry ? ' (dry run)' : ' removed').'.');
            if (! $dry) {
                foreach ($ids->chunk(1000) as $chunk) {
                    DB::table('vehicles')->whereIn('id', $chunk->all())->delete();
                }
            }
        }

        // Import working rows are only needed for review; keep the run summary.
        $old = SupplierImport::query()
            ->whereIn('status', [SupplierImport::STATUS_COMPLETED, SupplierImport::STATUS_CANCELLED, SupplierImport::STATUS_FAILED])
            ->where('finished_at', '<', now()->subDays(30));
        if (! $dry) {
            foreach ($old->get() as $import) {
                $import->rows()->delete();
                if ($import->file_path && str_starts_with($import->file_path, Storage::disk('local')->path(''))) {
                    @unlink($import->file_path);
                }
            }
        }

        return self::SUCCESS;
    }
}
