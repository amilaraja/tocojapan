<?php

namespace App\Console\Commands\Suppliers;

use App\Models\Supplier;
use App\Support\LiteSpeedCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RepriceSupplierStock extends Command
{
    protected $signature = 'suppliers:reprice
        {supplier? : Supplier slug (default: every supplier with a feed)}
        {--remargin : Apply the current supplier / site margin to every vehicle (default: keep each vehicle\'s own margin, follow the exchange rate only)}';

    protected $description = 'Recalculate FOB prices of supplier stock from the stored supplier price, current exchange rate and pricing rule.';

    public function handle(): int
    {
        $suppliers = Supplier::query()
            ->where('is_own_stock', false)
            ->when($this->argument('supplier'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        $changedTotal = 0;
        foreach ($suppliers as $supplier) {
            $rate = $supplier->sourceRateToUsd();
            if (! $rate) {
                $this->warn("{$supplier->name}: no exchange rate for {$supplier->setting('source_currency')} — skipped.");

                continue;
            }

            $changed = 0;
            DB::table('vehicles')
                ->where('supplier_id', $supplier->id)
                ->where('sync_locked', false)
                ->whereNull('deleted_at')
                ->whereNotNull('supplier_meta')
                ->select(['id', 'price_fob', 'source_price', 'supplier_meta'])
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($supplier, $rate, &$changed) {
                    $remargin = (bool) $this->option('remargin');
                    $current = $supplier->effectiveMargin();
                    $batch = [];
                    foreach ($rows as $row) {
                        $meta = json_decode((string) $row->supplier_meta, true) ?: [];
                        // Re-choose the source price too, so changing price basis / cap in
                        // the supplier settings takes effect without a new file.
                        $source = $supplier->chooseSourcePrice($meta['retail_price'] ?? null, $meta['wholesale_price'] ?? null);
                        $stored = is_array($meta['margin'] ?? null) ? $meta['margin'] : null;
                        $margin = $remargin || ! $stored
                            ? $current
                            : ['percent' => (float) $stored['percent'], 'fixed_usd' => (float) $stored['fixed_usd']];
                        $new = $supplier->fobFromSource($source, $rate, $margin);
                        $old = $row->price_fob !== null ? (float) $row->price_fob : null;
                        $metaChanged = $stored != $margin;
                        if ($new === $old && (float) $row->source_price === (float) $source && ! $metaChanged) {
                            continue;
                        }
                        $meta['margin'] = $margin;
                        $batch[(int) $row->id] = [$source, $new, $metaChanged ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null];
                    }
                    $this->writeBatch($batch);
                    $changed += count($batch);
                });

            $this->info("{$supplier->name}: {$changed} price(s) updated at {$rate} {$supplier->setting('source_currency')}/USD.");
            $changedTotal += $changed;
        }

        if ($changedTotal > 0) {
            Cache::forget('sitemap.vehicles.xml');
            LiteSpeedCache::queuePurge();
        }

        return self::SUCCESS;
    }

    /**
     * One UPDATE … CASE per chunk instead of one query per vehicle, so a
     * 70 000-vehicle reprice fits in the queue worker's 50 s timeout.
     *
     * @param  array<int, array{0: ?float, 1: ?float, 2: ?string}>  $batch  id => [source price, FOB, new supplier_meta JSON or null]
     */
    private function writeBatch(array $batch): void
    {
        if ($batch === []) {
            return;
        }
        // Values are ints/floats computed here (never user input), so they are
        // inlined as numeric literals.
        $num = fn (?float $v) => $v === null ? 'NULL' : sprintf('%.2F', $v);
        $pdo = DB::getPdo();
        $src = $fob = $por = $meta = '';
        foreach ($batch as $id => [$source, $new, $json]) {
            $src .= ' WHEN '.(int) $id.' THEN '.$num($source);
            $fob .= ' WHEN '.(int) $id.' THEN '.$num($new);
            $por .= ' WHEN '.(int) $id.' THEN '.($new === null ? 1 : 0);
            // Meta JSON is our own encoding, quoted by PDO.
            $meta .= ' WHEN '.(int) $id.' THEN '.($json === null ? 'supplier_meta' : $pdo->quote($json));
        }
        DB::table('vehicles')->whereIn('id', array_keys($batch))->update([
            'source_price' => DB::raw('CASE id'.$src.' END'),
            'price_fob' => DB::raw('CASE id'.$fob.' END'),
            'price_on_request' => DB::raw('CASE id'.$por.' END'),
            'supplier_meta' => DB::raw('CASE id'.$meta.' END'),
            'updated_at' => now(),
        ]);
    }
}
