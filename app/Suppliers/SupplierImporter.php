<?php

namespace App\Suppliers;

use App\Models\BodyType;
use App\Models\Make;
use App\Models\Supplier;
use App\Models\SupplierImport;
use App\Models\SupplierImportRow;
use App\Models\VehicleModel;
use App\Suppliers\Feeds\OnePriceCsvFeed;
use App\Suppliers\Feeds\SupplierFeed;
use App\Support\LiteSpeedCache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Staged sync of a supplier stock file into the vehicles table.
 *
 *   staging    – parse the file into supplier_import_rows (resumable by byte offset)
 *   previewing – compare every row with the existing vehicle → new / update /
 *                unchanged / relist / locked / skipped, and count what a full
 *                import would delist
 *   previewed  – waits for an admin to approve (or auto-approves from the CLI)
 *   applying   – write vehicles in batches
 *   delisting  – full imports only: live supplier stock missing from the file
 *                becomes status "delisted" (never deleted, so URLs keep working)
 *
 * Vehicles are matched on (supplier_id, supplier_ref); the slug is created
 * once and never rewritten, so permalinks survive every re-import.
 * Work is done in slices so a queue worker with a 50 s timeout can carry a
 * 70 000-row file across several runs.
 */
class SupplierImporter
{
    public const STAGE_CHUNK = 1000;

    public const ROW_CHUNK = 500;

    public const MAX_ERROR_SAMPLES = 100;

    /** Feed maker names that belong to an existing make. Keys/values are slugs. */
    public const MAKE_ALIASES = [
        'america-honda' => 'honda', 'america-mitsubishi' => 'mitsubishi', 'america-nissan' => 'nissan',
        'america-suzuki' => 'suzuki', 'america-toyota' => 'toyota', 'chrysler-jeep' => 'jeep',
        'amc-jeep' => 'jeep', 'ford-japan' => 'ford', 'infinity' => 'infiniti', 'mcc-smart' => 'smart',
        'usa-other' => 'other', 'mercedes-amg' => 'mercedes-benz', 'amg' => 'mercedes-benz',
        'mercedes-maybach' => 'mercedes-benz',
    ];

    /** Display name for makes created through an alias (the feed's own spelling is wrong or regional). */
    public const MAKE_NAMES = [
        'smart' => 'SMART', 'infiniti' => 'INFINITI', 'other' => 'OTHER', 'jeep' => 'JEEP',
    ];

    /** @var array<string, int> */
    private array $makeIds = [];

    /** @var array<string, int> */
    private array $modelIds = [];

    /** @var array<string, int>|null */
    private ?array $bodyTypeIds = null;

    private ?float $rate = null;

    public static function feedFor(Supplier $supplier): SupplierFeed
    {
        return match ($supplier->feed_format) {
            'oneprice_csv' => new OnePriceCsvFeed,
            default => throw new \InvalidArgumentException("Supplier {$supplier->name} has no importable feed format."),
        };
    }

    /**
     * Advance the import until it needs approval, finishes, or the deadline
     * (unix time, null = no limit) passes. Safe to call repeatedly.
     */
    public function run(SupplierImport $import, ?float $deadline = null): SupplierImport
    {
        try {
            while (! $this->timeUp($deadline)) {
                $import->refresh();
                $progressed = match ($import->status) {
                    SupplierImport::STATUS_QUEUED => $this->begin($import),
                    SupplierImport::STATUS_STAGING => $this->stageSlice($import),
                    SupplierImport::STATUS_PREVIEWING => $this->previewSlice($import),
                    SupplierImport::STATUS_APPLYING => $this->applySlice($import),
                    SupplierImport::STATUS_DELISTING => $this->delist($import),
                    default => false,
                };
                if (! $progressed) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Supplier import failed', ['import' => $import->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $import->forceFill([
                'status' => SupplierImport::STATUS_FAILED,
                'message' => Str::limit($e->getMessage(), 1000),
                'finished_at' => now(),
            ])->save();
        }

        return $import->refresh();
    }

    /** Admin approval of a previewed import. */
    public function approve(SupplierImport $import, bool $confirmDelist = false): void
    {
        if (! $import->isAwaitingApproval()) {
            throw new \RuntimeException('This import is not waiting for approval.');
        }
        if ($import->needsDelistConfirmation() && ! $confirmDelist) {
            throw new \RuntimeException('This file would delist an unusually large part of the stock. Confirm the delisting to continue.');
        }
        $import->forceFill([
            'status' => SupplierImport::STATUS_APPLYING,
            'approved_at' => now(),
            'processed_rows' => 0,
        ])->save();
    }

    public function cancel(SupplierImport $import): void
    {
        if (in_array($import->status, [SupplierImport::STATUS_APPLYING, SupplierImport::STATUS_DELISTING, SupplierImport::STATUS_COMPLETED], true)) {
            throw new \RuntimeException('An import that is already being applied cannot be cancelled.');
        }
        $import->rows()->delete();
        $import->forceFill(['status' => SupplierImport::STATUS_CANCELLED, 'finished_at' => now()])->save();
    }

    /**
     * Zipped (.zip, first file inside) or gzipped (.gz) uploads are unpacked
     * next to the original — supplier CSVs shrink ~10× compressed, which
     * keeps them under the web upload limit.
     */
    public static function unpack(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $target = preg_replace('/\.(zip|gz)$/i', '', $path);
        if ($ext === 'gz') {
            $in = gzopen($path, 'rb');
            $out = fopen($target, 'wb');
            if ($in === false || $out === false) {
                throw new \RuntimeException('Could not unpack the .gz file.');
            }
            while (! gzeof($in)) {
                fwrite($out, (string) gzread($in, 1 << 20));
            }
            gzclose($in);
            fclose($out);

            return $target;
        }
        if ($ext === 'zip') {
            if (! class_exists(\ZipArchive::class)) {
                throw new \RuntimeException('Zip files are not supported on this server — upload a .csv or .gz file.');
            }
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Could not open the .zip file.');
            }
            $entry = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (! str_ends_with($name, '/') && ! str_starts_with(basename($name), '.')) {
                    $entry = $name;
                    break;
                }
            }
            if ($entry === null) {
                throw new \RuntimeException('The .zip file is empty.');
            }
            $target = preg_replace('/\.csv$/i', '', $target).'.csv';
            $stream = $zip->getStream($entry);
            $out = fopen($target, 'wb');
            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);
            $zip->close();

            return $target;
        }

        return $path;
    }

    private function timeUp(?float $deadline): bool
    {
        return $deadline !== null && microtime(true) >= $deadline;
    }

    private function begin(SupplierImport $import): bool
    {
        if (! $import->file_path || ! is_file($import->file_path)) {
            throw new \RuntimeException('The uploaded file could not be found.');
        }
        $import->file_path = self::unpack($import->file_path);
        $import->rows()->delete();
        $import->forceFill([
            'status' => SupplierImport::STATUS_STAGING,
            'started_at' => now(),
            'total_rows' => 0, 'valid_rows' => 0, 'error_rows' => 0, 'processed_rows' => 0,
            // Keep the run options (auto_apply / force from the command line).
            'stats' => ['file_offset' => 0, 'line_no' => 0, 'duplicates' => 0,
                'auto_apply' => (bool) $import->stat('auto_apply', false), 'force' => (bool) $import->stat('force', false),
                // Margin used for every vehicle in this file: the import's own
                // override, else the supplier's, else the site default.
                'margin_override' => $import->stat('margin_override', null),
                'margin' => $import->supplier->effectiveMargin($import->stat('margin_override', null))],
            'errors_sample' => [],
        ])->save();

        return true;
    }

    private function stageSlice(SupplierImport $import): bool
    {
        $feed = self::feedFor($import->supplier);
        $chunk = $feed->read($import->file_path, (int) $import->stat('file_offset'), self::STAGE_CHUNK, (int) $import->stat('line_no'));

        $errors = $import->errors_sample ?? [];
        $insert = [];
        $lastLine = (int) $import->stat('line_no');
        foreach ($chunk['rows'] as $row) {
            $lastLine = $row['line'];
            if ($row['error'] !== null) {
                $import->error_rows++;
                if (count($errors) < self::MAX_ERROR_SAMPLES) {
                    $errors[] = ['line' => $row['line'], 'ref' => $row['ref'], 'error' => $row['error']];
                }

                continue;
            }
            $json = json_encode($row['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $insert[$row['ref']] = [
                'supplier_import_id' => $import->id,
                'supplier_ref' => $row['ref'],
                'line_no' => $row['line'],
                'payload' => $json,
                'hash' => sha1($json),
            ];
        }

        if ($insert) {
            $before = $import->rows()->count();
            SupplierImportRow::query()->upsert(array_values($insert), ['supplier_import_id', 'supplier_ref'], ['line_no', 'payload', 'hash']);
            $added = $import->rows()->count() - $before;
            $import->mergeStats(['duplicates' => (int) $import->stat('duplicates') + (count($insert) - $added)]);
        }

        $import->total_rows += count($chunk['rows']);
        $import->errors_sample = $errors;
        $import->mergeStats(['file_offset' => $chunk['offset'], 'line_no' => $lastLine]);

        if ($chunk['eof']) {
            $import->valid_rows = $import->rows()->count();
            if ($import->valid_rows === 0) {
                $import->status = SupplierImport::STATUS_FAILED;
                $import->message = 'No usable vehicles were found in the file. Check that it is the right supplier file.';
                $import->finished_at = now();
            } else {
                $import->status = SupplierImport::STATUS_PREVIEWING;
                $import->processed_rows = 0;
                $import->mergeStats(['cursor' => 0, 'dims' => []]);
            }
        }
        $import->save();

        return true;
    }

    private function previewSlice(SupplierImport $import): bool
    {
        $supplier = $import->supplier;
        $rows = $import->rows()->where('id', '>', (int) $import->stat('cursor'))->orderBy('id')->limit(self::ROW_CHUNK)->get();

        if ($rows->isEmpty()) {
            return $this->finishPreview($import);
        }

        $vehicles = DB::table('vehicles')
            ->where('supplier_id', $supplier->id)
            ->whereIn('supplier_ref', $rows->pluck('supplier_ref'))
            ->get(['id', 'supplier_ref', 'status', 'price_fob', 'supplier_hash', 'sync_locked', 'deleted_at'])
            ->keyBy('supplier_ref');

        $counts = $import->stats['counts'] ?? [];
        $dims = $import->stats['dims'] ?? [];
        $updates = [];
        foreach ($rows as $row) {
            $p = $row->payload;
            $newPrice = $supplier->fobFromSource($supplier->chooseSourcePrice($p['retail_price'] ?? null, $p['wholesale_price'] ?? null), $this->rate($supplier), $this->margin($import));
            $v = $vehicles->get($row->supplier_ref);
            $old = $v?->price_fob !== null ? (float) $v->price_fob : null;

            $action = match (true) {
                $v === null => 'new',
                $v->deleted_at !== null => 'skipped',
                (bool) $v->sync_locked => $v->status === 'delisted' ? 'relist' : 'locked',
                $v->status === 'delisted' => 'relist',
                $v->supplier_hash === $row->hash && $old === $newPrice => 'unchanged',
                default => 'update',
            };
            $counts[$action] = ($counts[$action] ?? 0) + 1;
            if ($action === 'update' && $old !== null && $newPrice !== null && $old !== $newPrice) {
                $key = $newPrice > $old ? 'price_up' : 'price_down';
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            if (! empty($p['length_cm']) && ! empty($p['width_cm']) && ! empty($p['height_cm'])) {
                $k = $p['make'].'|'.$p['model'];
                $dims[$k] = [
                    ($dims[$k][0] ?? 0) + $p['length_cm'] * $p['width_cm'] * $p['height_cm'] / 1_000_000,
                    ($dims[$k][1] ?? 0) + 1,
                ];
            }

            $updates[] = [
                'id' => $row->id,
                'supplier_import_id' => $row->supplier_import_id,
                'supplier_ref' => $row->supplier_ref,
                'line_no' => $row->line_no,
                'payload' => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'hash' => $row->hash,
                'action' => $action,
                'vehicle_id' => $v?->id,
                'old_price_fob' => $old,
                'new_price_fob' => $newPrice,
            ];
        }
        SupplierImportRow::query()->upsert($updates, ['id'], ['action', 'vehicle_id', 'old_price_fob', 'new_price_fob']);

        $import->processed_rows += $rows->count();
        $import->mergeStats(['cursor' => $rows->last()->id, 'counts' => $counts, 'dims' => $dims]);
        $import->save();

        return true;
    }

    private function finishPreview(SupplierImport $import): bool
    {
        $supplier = $import->supplier;
        $live = DB::table('vehicles')->where('supplier_id', $supplier->id)->where('status', 'published')->whereNull('deleted_at')->count();
        $toDelist = $import->mode === 'full' ? $this->missingQuery($import)->count() : 0;
        $guard = (float) $supplier->setting('delist_guard_percent');
        $tripped = $live > 0 && $guard > 0 && ($toDelist / $live * 100) > $guard;

        $import->mergeStats([
            'live_before' => $live,
            'to_delist' => $toDelist,
            'delist_guard_tripped' => $tripped,
            'exchange_rate' => $this->rate($supplier),
        ]);
        $import->status = SupplierImport::STATUS_PREVIEWED;
        $import->processed_rows = 0;

        $autoApply = (bool) $import->stat('auto_apply', false);
        $force = (bool) $import->stat('force', false);
        if ($autoApply && (! $tripped || $force)) {
            $import->status = SupplierImport::STATUS_APPLYING;
            $import->approved_at = now();
        }
        $import->save();

        return $import->status === SupplierImport::STATUS_APPLYING;
    }

    /** Live vehicles of this supplier whose ref is not in the import file. */
    private function missingQuery(SupplierImport $import): Builder
    {
        return DB::table('vehicles as v')
            ->where('v.supplier_id', $import->supplier_id)
            ->where('v.status', 'published')
            ->whereNull('v.deleted_at')
            ->whereNotExists(fn ($q) => $q->from('supplier_import_rows as r')
                ->where('r.supplier_import_id', $import->id)
                ->whereColumn('r.supplier_ref', 'v.supplier_ref'));
    }

    private function applySlice(SupplierImport $import): bool
    {
        $supplier = $import->supplier;
        $rows = $import->rows()->where('applied', false)->orderBy('id')->limit(self::ROW_CHUNK)->get();

        if ($rows->isEmpty()) {
            $import->status = $import->mode === 'full' ? SupplierImport::STATUS_DELISTING : SupplierImport::STATUS_COMPLETED;
            if ($import->status === SupplierImport::STATUS_COMPLETED) {
                $this->finish($import);
            }
            $import->save();

            return $import->status === SupplierImport::STATUS_DELISTING;
        }

        $now = now();
        $autoPublish = (bool) $supplier->setting('auto_publish_new');
        $dims = $import->stats['dims'] ?? [];
        $margin = $this->margin($import);
        $inserts = [];
        $touchIds = [];
        $applied = $import->stats['applied'] ?? [];

        DB::transaction(function () use ($rows, $supplier, $now, $autoPublish, $dims, $margin, &$inserts, &$touchIds, &$applied) {
            foreach ($rows as $row) {
                $p = $row->payload;
                $action = $row->action;
                $applied[$action] = ($applied[$action] ?? 0) + 1;

                if ($action === 'skipped') {
                    continue;
                }
                if ($action === 'unchanged') {
                    $touchIds[] = $row->vehicle_id;

                    continue;
                }

                $fields = $this->vehicleFields($supplier, $p, $row->hash, $row->new_price_fob !== null ? (float) $row->new_price_fob : null, $dims, $now, $margin);

                if ($action === 'new') {
                    $published = $autoPublish ? 'published' : 'draft';
                    $inserts[] = $fields + [
                        'supplier_id' => $supplier->id,
                        'supplier_ref' => $row->supplier_ref,
                        'stock_no' => trim(($supplier->stock_prefix ? $supplier->stock_prefix.'-' : '').$row->supplier_ref),
                        'slug' => $this->uniqueSlug($fields['title'].' '.($supplier->stock_prefix ?: $supplier->slug).' '.$row->supplier_ref),
                        'status' => $published,
                        'published_at' => $autoPublish ? $now : null,
                        'is_featured' => false,
                        'price_fob_discount' => null,
                        'currency' => 'USD',
                        'created_at' => $now,
                    ];

                    continue;
                }

                $update = match ($action) {
                    'locked' => ['supplier_synced_at' => $now, 'updated_at' => $now],
                    'relist' => ($row->vehicle_id && DB::table('vehicles')->where('id', $row->vehicle_id)->value('sync_locked')
                        ? ['supplier_synced_at' => $now, 'updated_at' => $now]
                        : $fields) + ['status' => 'published', 'delisted_at' => null, 'published_at' => $now],
                    default => $fields,
                };
                DB::table('vehicles')->where('id', $row->vehicle_id)->update($update);
            }

            foreach (array_chunk($inserts, 250) as $batch) {
                DB::table('vehicles')->insert($batch);
            }
            foreach (array_chunk(array_filter($touchIds), 1000) as $ids) {
                DB::table('vehicles')->whereIn('id', $ids)->update(['supplier_synced_at' => $now]);
            }
            SupplierImportRow::query()->whereIn('id', $rows->pluck('id'))->update(['applied' => true]);
        });

        $import->processed_rows += $rows->count();
        $import->mergeStats(['applied' => $applied, 'makes_created' => (int) $import->stat('makes_created') + $this->createdMakes, 'models_created' => (int) $import->stat('models_created') + $this->createdModels]);
        $this->createdMakes = $this->createdModels = 0;
        $import->save();

        return true;
    }

    private int $createdMakes = 0;

    private int $createdModels = 0;

    private function delist(SupplierImport $import): bool
    {
        $ids = $this->missingQuery($import)->pluck('v.id');
        $now = now();
        foreach ($ids->chunk(1000) as $chunk) {
            DB::table('vehicles')->whereIn('id', $chunk->all())->update(['status' => 'delisted', 'delisted_at' => $now, 'updated_at' => $now]);
        }
        $applied = $import->stats['applied'] ?? [];
        $applied['delisted'] = $ids->count();
        $import->mergeStats(['applied' => $applied]);
        $import->status = SupplierImport::STATUS_COMPLETED;
        $this->finish($import);
        $import->save();

        return false;
    }

    private function finish(SupplierImport $import): void
    {
        $import->finished_at = now();
        $import->mergeStats([
            'live_after' => DB::table('vehicles')->where('supplier_id', $import->supplier_id)->where('status', 'published')->whereNull('deleted_at')->count(),
            'dims' => null,
        ]);
        foreach (['sitemap.index.xml', 'sitemap.vehicles.xml', 'home.total_published', 'vehicles.published_counts.make_id', 'vehicles.published_counts.body_type_id'] as $key) {
            Cache::forget($key);
        }
        LiteSpeedCache::queuePurge();
    }

    /**
     * Columns the supplier owns. Never includes slug, status, featured flag,
     * description or SEO — those stay under admin control.
     *
     * @param  array<string, mixed>  $p
     * @param  array<string, array{0: float, 1: int}>  $dims
     * @return array<string, mixed>
     */
    private function vehicleFields(Supplier $supplier, array $p, string $hash, ?float $priceFob, array $dims, \DateTimeInterface $now, array $margin): array
    {
        $makeId = $this->makeId($p['make']);
        $modelId = $this->modelId($makeId, $p['model']);
        $make = $this->makeNames[$makeId] ?? $p['make'];
        $model = $this->modelNames[$modelId] ?? $p['model'];

        $m3 = null;
        $meta = $p['meta'] ?? [];
        if (! empty($p['length_cm']) && ! empty($p['width_cm']) && ! empty($p['height_cm'])) {
            $m3 = round($p['length_cm'] * $p['width_cm'] * $p['height_cm'] / 1_000_000, 4);
        } elseif (isset($dims[$p['make'].'|'.$p['model']]) && $dims[$p['make'].'|'.$p['model']][1] > 0) {
            // Size missing: estimate shipping volume from the same model elsewhere in the feed.
            [$sum, $n] = $dims[$p['make'].'|'.$p['model']];
            $m3 = round($sum / $n, 4);
            $meta['m3_estimated'] = true;
        }

        $source = $supplier->chooseSourcePrice($p['retail_price'] ?? null, $p['wholesale_price'] ?? null);
        $meta['retail_price'] = $p['retail_price'] ?? null;
        // The nightly reprice keeps this margin and only follows the exchange rate.
        $meta['margin'] = $margin;
        $meta['wholesale_price'] = $p['wholesale_price'] ?? null;

        return [
            'title' => mb_substr(trim("{$p['year']} {$make} {$model}"), 0, 180),
            'make_id' => $makeId,
            'vehicle_model_id' => $modelId,
            'grade' => $p['grade'] ?? null,
            'location' => $p['location'] ?? null,
            'body_type_id' => isset($p['body_type']) ? ($this->bodyTypeIds()[$p['body_type']] ?? null) : null,
            'year_first_reg' => $p['year'],
            'registration_month' => $p['month'] ?? null,
            'mileage_km' => $p['mileage_km'] ?? null,
            'engine_cc' => $p['engine_cc'] ?? null,
            'fuel' => $p['fuel'] ?? null,
            'transmission' => $p['transmission'] ?? null,
            'drive' => $p['drive'] ?? null,
            'steering_side' => $p['steering_side'] ?? 'right',
            'exterior_color' => $p['exterior_color'] ?? null,
            'doors' => $p['doors'] ?? null,
            'seats' => $p['seats'] ?? null,
            'length_cm' => $p['length_cm'] ?? null,
            'width_cm' => $p['width_cm'] ?? null,
            'height_cm' => $p['height_cm'] ?? null,
            'm3' => $m3,
            'chassis_number' => $p['chassis_number'] ?? null,
            'model_code' => $p['model_code'] ?? null,
            'features' => ! empty($p['features']) ? json_encode($p['features'], JSON_UNESCAPED_UNICODE) : null,
            'external_photos' => json_encode(array_values($p['photos'] ?? []), JSON_UNESCAPED_SLASHES),
            'supplier_meta' => json_encode(array_filter($meta, fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_price' => $source,
            'source_currency' => $supplier->setting('source_currency'),
            'price_fob' => $priceFob,
            'price_on_request' => $priceFob === null,
            'supplier_hash' => $hash,
            'supplier_synced_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @var array<int, string> */
    private array $makeNames = [];

    /** @var array<int, string> */
    private array $modelNames = [];

    private function makeId(string $name): int
    {
        $slug = Str::slug($name);
        $slug = self::MAKE_ALIASES[$slug] ?? $slug;
        if (isset($this->makeIds[$slug])) {
            return $this->makeIds[$slug];
        }
        $make = Make::query()->where('slug', $slug)->first();
        if (! $make) {
            $make = Make::query()->create([
                'slug' => $slug,
                'name' => self::MAKE_NAMES[$slug] ?? $name,
                'is_active' => true,
                'sort_order' => 900,
            ]);
            $this->createdMakes++;
        }
        $this->makeNames[$make->id] = $make->name;

        return $this->makeIds[$slug] = $make->id;
    }

    private function modelId(int $makeId, string $name): int
    {
        $slug = Str::slug($name) ?: 'other';
        $key = $makeId.'|'.$slug;
        if (isset($this->modelIds[$key])) {
            return $this->modelIds[$key];
        }
        $model = VehicleModel::query()->where('make_id', $makeId)->where('slug', $slug)->first();
        if (! $model) {
            $model = VehicleModel::query()->create([
                'make_id' => $makeId,
                'slug' => $slug,
                'name' => $name,
                'is_active' => true,
                'sort_order' => 900,
            ]);
            $this->createdModels++;
        }
        $this->modelNames[$model->id] = $model->name;

        return $this->modelIds[$key] = $model->id;
    }

    /** @return array<string, int> */
    private function bodyTypeIds(): array
    {
        return $this->bodyTypeIds ??= BodyType::query()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array{percent: float, fixed_usd: float} */
    private function margin(SupplierImport $import): array
    {
        $m = $import->stat('margin', null);

        return is_array($m) ? ['percent' => (float) $m['percent'], 'fixed_usd' => (float) $m['fixed_usd']]
            : $import->supplier->effectiveMargin($import->stat('margin_override', null));
    }

    private function rate(Supplier $supplier): ?float
    {
        return $this->rate ??= $supplier->sourceRateToUsd();
    }

    /** @var array<string, true> */
    private array $reservedSlugs = [];

    private function uniqueSlug(string $base): string
    {
        $base = Str::limit(Str::slug($base), 200, '');
        $slug = $base;
        $i = 2;
        while (isset($this->reservedSlugs[$slug]) || DB::table('vehicles')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $this->reservedSlugs[$slug] = true;

        return $slug;
    }
}
