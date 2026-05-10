<?php

namespace App\Console\Commands\Migration;

use App\Models\Make;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('migrate:wp-vehicles {--dry-run} {--limit=0 : Stop after N products (0 = no limit)} {--exclude-auction : Skip products tagged as auction stock (the One Price system)}')]
#[Description('Import owner-stock vehicles (WC products + ACF postmeta) into the vehicles table.')]
class MigrateWpVehicles extends Command
{
    /** @var array<string, string>  postmeta_key => vehicles column */
    private const POSTMETA_TO_COLUMN = [
        'ref_no' => 'ref_no',
        'year' => 'year_first_reg',
        'mileage' => 'mileage_km',
        'engine_cc' => 'engine_cc',
        'fuel' => 'fuel',
        'transmission' => 'transmission',
        'drive' => 'drive',
        'steering_side' => 'steering_side',
        'exterior_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'doors' => 'doors',
        'seats' => 'seats',
        'length_cm' => 'length_cm',
        'width_cm' => 'width_cm',
        'height_cm' => 'height_cm',
        'm3' => 'm3',
        'warranty_period' => 'warranty_period',
    ];

    public function handle(MigrationReporter $reporter): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $excludeAuction = (bool) $this->option('exclude-auction');

        $reporter->open('vehicles');

        $q = DB::connection('wp')->table('posts')
            ->where('post_type', 'product')
            ->where('post_status', 'publish');

        if ($excludeAuction) {
            // The One Price/auction inventory is intentionally excluded per the
            // owner's scope decision (project_one_price_excluded memory).
            $q->whereNotIn('ID', function ($sub) {
                $sub->from('term_relationships as tr')
                    ->join('term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                    ->join('terms as t', 't.term_id', '=', 'tt.term_id')
                    ->where('tt.taxonomy', 'product_tag')
                    ->whereIn('t.slug', ['auction', 'one-price', 'auction-stock'])
                    ->select('tr.object_id');
            });
        }

        $total = (clone $q)->count();
        $this->info("Found {$total} WC products. Streaming…");
        $bar = $this->output->createProgressBar($total);

        $written = 0;
        $skipped = 0;
        $errors = [];

        $q->orderBy('ID')->lazy(200)->each(function ($post) use ($dry, $bar, &$written, &$skipped, &$errors, $limit) {
            if ($limit > 0 && $written + $skipped >= $limit) {
                return false;
            }

            try {
                $meta = DB::connection('wp')->table('postmeta')
                    ->where('post_id', $post->ID)
                    ->pluck('meta_value', 'meta_key');

                $refNo = (string) ($meta['ref_no'] ?? 'TJ-WP-'.$post->ID);

                // Resolve make/model via product_cat term relationships.
                $cats = DB::connection('wp')->table('term_relationships as tr')
                    ->join('term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                    ->join('terms as t', 't.term_id', '=', 'tt.term_id')
                    ->where('tr.object_id', $post->ID)
                    ->where('tt.taxonomy', 'product_cat')
                    ->select('t.slug', 't.name', 'tt.parent')
                    ->get();

                $makeSlug = optional($cats->firstWhere('parent', 0))->slug;
                $modelSlug = optional($cats->firstWhere(fn ($c) => $c->parent !== 0))->slug;

                $make = $makeSlug ? Make::where('slug', $makeSlug)->first() : null;
                $model = ($make && $modelSlug) ? VehicleModel::where('make_id', $make->id)->where('slug', $modelSlug)->first() : null;

                if (! $make || ! $model) {
                    $skipped++;
                    $errors[] = "post {$post->ID}: missing make/model (make={$makeSlug}, model={$modelSlug})";

                    return;
                }

                $attrs = [
                    'ref_no' => $refNo,
                    'slug' => $post->post_name ?: Str::slug($post->post_title.'-'.$refNo),
                    'title' => $post->post_title,
                    'description' => $post->post_content,
                    'status' => 'published',
                    'make_id' => $make->id,
                    'vehicle_model_id' => $model->id,
                    'price_fob' => (float) ($meta['_price'] ?? $meta['_regular_price'] ?? 0),
                    'currency' => 'USD',
                    'published_at' => $post->post_date_gmt,
                ];

                foreach (self::POSTMETA_TO_COLUMN as $metaKey => $col) {
                    if (isset($meta[$metaKey]) && $meta[$metaKey] !== '') {
                        $attrs[$col] = $meta[$metaKey];
                    }
                }

                // ACF feature groups stored as serialized arrays.
                $features = [];
                foreach (['comfort', 'safety', 'sound_system', 'seats', 'windows', 'other', 'other_selling_points'] as $group) {
                    $raw = $meta[$group] ?? null;
                    if (is_string($raw) && $raw !== '') {
                        $unserialized = @unserialize($raw);
                        if (is_array($unserialized)) {
                            $features[$group] = $unserialized;
                        }
                    }
                }
                if (! empty($features)) {
                    $attrs['features'] = $features;
                }

                if (! $dry) {
                    Vehicle::updateOrCreate(['ref_no' => $refNo], $attrs);
                }

                $written++;
            } catch (\Throwable $e) {
                $errors[] = "post {$post->ID}: ".$e->getMessage();
                $skipped++;
            } finally {
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $reporter->note('total_seen', $total);
        $reporter->note('written', $written);
        $reporter->note('skipped', $skipped);
        $reporter->note('errors_sample', array_slice($errors, 0, 50));
        $reporter->note('dry_run', $dry);
        $reporter->close('vehicles');

        $this->info(($dry ? 'DRY: ' : '')."Vehicles written={$written}, skipped={$skipped}");
        if ($errors !== []) {
            $this->warn(count($errors).' error(s) — see storage/migration-reports/.');
        }

        return self::SUCCESS;
    }
}
