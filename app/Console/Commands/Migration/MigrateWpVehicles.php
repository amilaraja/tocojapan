<?php

namespace App\Console\Commands\Migration;

use App\Models\Make;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('migrate:wp-vehicles
    {--dry-run}
    {--limit=0 : Stop after N products (0 = no limit)}
    {--exclude-auction : Skip products tagged as auction stock (the One Price system)}
    {--no-photos : Skip downloading featured + gallery images}
    {--uploads-path= : Absolute path to wp-content/uploads (defaults to env WP_UPLOADS_PATH)}')]
#[Description('Import owner-stock vehicles (WC products + ACF postmeta + featured/gallery images) into the vehicles table.')]
class MigrateWpVehicles extends Command
{
    /** @var array<string, string>  postmeta_key => vehicles column */
    // NB: chassis_no is handled manually above to disambiguate redacted
    // duplicates — don't add it here or it'll overwrite the computed ref_no.
    private const POSTMETA_TO_COLUMN = [
        'year' => 'year_first_reg',
        'mileage' => 'mileage_km',
        'engine_cc' => 'engine_cc',
        'fuel' => 'fuel',
        'transmission' => 'transmission',
        'drive' => 'drive',
        'steering_side' => 'steering_side',
        'body_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'doors' => 'doors',
        'seats' => 'seats',
        'm3' => 'm3',
        'warranty_period' => 'warranty_period',
    ];

    public function handle(MigrationReporter $reporter): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $excludeAuction = (bool) $this->option('exclude-auction');
        $importPhotos = ! (bool) $this->option('no-photos');
        $uploadsPath = (string) ($this->option('uploads-path') ?: env('WP_UPLOADS_PATH', ''));

        if ($importPhotos && $uploadsPath === '') {
            $this->warn('No --uploads-path set and WP_UPLOADS_PATH env is empty — photos will be skipped.');
            $importPhotos = false;
        }
        if ($importPhotos && ! is_dir($uploadsPath)) {
            $this->warn("Uploads path {$uploadsPath} does not exist — photos will be skipped.");
            $importPhotos = false;
        }

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
        $this->info("Found {$total} WC products. photos=".($importPhotos ? 'yes' : 'no').' Streaming…');
        $bar = $this->output->createProgressBar($total);

        $written = 0;
        $skipped = 0;
        $photosWritten = 0;
        $errors = [];

        $q->orderBy('ID')->lazy(50)->each(function ($post) use ($dry, $bar, $importPhotos, $uploadsPath, &$written, &$skipped, &$photosWritten, &$errors, $limit) {
            if ($limit > 0 && $written + $skipped >= $limit) {
                return false;
            }

            try {
                $meta = DB::connection('wp')->table('postmeta')
                    ->where('post_id', $post->ID)
                    ->pluck('meta_value', 'meta_key');

                $rawChassis = (string) ($meta['chassis_no'] ?? $meta['ref_no'] ?? '');
                // WP redacts chassis numbers (e.g. "HA4-238****") so several
                // distinct vehicles can share the same string. Append the WP
                // post id whenever the chassis is asterisk-redacted OR empty,
                // so updateOrCreate on ref_no doesn't collapse them.
                $refNo = $rawChassis === '' || str_contains($rawChassis, '*')
                    ? ($rawChassis !== '' ? $rawChassis.'-'.$post->ID : 'TJ-WP-'.$post->ID)
                    : $rawChassis;

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

                // WP often has unicode chars (★, ⁕) in post_name — slug them out.
                // Always append the WP post id so distinct vehicles with redacted
                // chassis numbers don't collide on the unique slug index.
                $cleanSlug = Str::slug($post->post_title.' '.$post->ID) ?: 'tj-wp-'.$post->ID;

                $attrs = [
                    'ref_no' => $refNo,
                    'slug' => $cleanSlug,
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

                // Parse lwh "3.25×1.39×1.75" (metres) → length/width/height in cm.
                if (! empty($meta['lwh'])) {
                    [$l, $w, $h] = array_pad(self::parseLwh((string) $meta['lwh']), 3, null);
                    if ($l) {
                        $attrs['length_cm'] = $l;
                    }
                    if ($w) {
                        $attrs['width_cm'] = $w;
                    }
                    if ($h) {
                        $attrs['height_cm'] = $h;
                    }
                }

                // ACF feature groups stored as serialized arrays.
                $features = [];
                foreach (['comfort', 'safety', 'sound_system', 'seats', 'windows', 'other', 'other_selling_points'] as $group) {
                    $raw = $meta[$group] ?? null;
                    if (is_string($raw) && $raw !== '') {
                        $unserialized = @unserialize($raw);
                        if (is_array($unserialized) && $unserialized !== []) {
                            $features[$group] = $unserialized;
                        }
                    }
                }
                if (! empty($features)) {
                    $attrs['features'] = $features;
                }

                if (! $dry) {
                    $vehicle = Vehicle::updateOrCreate(['ref_no' => $refNo], $attrs);

                    if ($importPhotos) {
                        $photosWritten += $this->importPhotos($vehicle, $post->ID, $meta, $uploadsPath, $errors);
                    }
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
        $reporter->note('photos_written', $photosWritten);
        $reporter->note('errors_sample', array_slice($errors, 0, 50));
        $reporter->note('dry_run', $dry);
        $reporter->close('vehicles');

        $this->info(($dry ? 'DRY: ' : '')."Vehicles written={$written}, skipped={$skipped}, photos={$photosWritten}");
        if ($errors !== []) {
            $this->warn(count($errors).' error(s) — see storage/migration-reports/.');
        }

        return self::SUCCESS;
    }

    /**
     * Import featured + gallery images for a single vehicle from WP attachments.
     *
     * @param  Collection<string, mixed>  $meta
     * @param  array<int, string>  $errors
     * @return int number of photos written
     */
    private function importPhotos(Vehicle $vehicle, int $wpPostId, $meta, string $uploadsPath, array &$errors): int
    {
        $featuredId = (int) ($meta['_thumbnail_id'] ?? 0);
        $galleryCsv = (string) ($meta['_product_image_gallery'] ?? '');
        $galleryIds = array_filter(array_map('intval', explode(',', $galleryCsv)));

        // Featured first so it ends up as the lead photo.
        $attachmentIds = $featuredId > 0 ? array_merge([$featuredId], $galleryIds) : $galleryIds;
        if ($attachmentIds === []) {
            return 0;
        }

        // Resolve all attachment file paths in one query.
        $relPaths = DB::connection('wp')->table('postmeta')
            ->where('meta_key', '_wp_attached_file')
            ->whereIn('post_id', $attachmentIds)
            ->pluck('meta_value', 'post_id');

        // Already-imported attachment ids on this vehicle (idempotency).
        $existingNames = $vehicle->getMedia('photos')->pluck('name')->all();

        $count = 0;
        foreach ($attachmentIds as $attId) {
            $name = 'wp-'.$attId;
            if (in_array($name, $existingNames, true)) {
                continue;
            }
            $relPath = $relPaths[$attId] ?? null;
            if (! $relPath) {
                continue;
            }
            $abs = rtrim($uploadsPath, '/').'/'.ltrim($relPath, '/');
            if (! is_file($abs)) {
                $errors[] = "post {$wpPostId}: attachment {$attId} file missing at {$abs}";

                continue;
            }
            try {
                $vehicle
                    ->addMedia($abs)
                    ->preservingOriginal()
                    ->usingName($name)
                    ->usingFileName(basename($abs))
                    ->toMediaCollection('photos');
                $count++;
            } catch (\Throwable $e) {
                $errors[] = "post {$wpPostId}: attachment {$attId} failed: ".$e->getMessage();
            }
        }

        return $count;
    }

    /**
     * Parse an LWH string like "3.25×1.39×1.75" or "3.25x1.39x1.75" (metres) into cm.
     *
     * @return array<int, ?float>
     */
    private static function parseLwh(string $raw): array
    {
        $clean = str_replace(['×', 'X', '*'], 'x', $raw);
        $parts = array_map('trim', explode('x', $clean));
        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || ! is_numeric($p)) {
                $out[] = null;

                continue;
            }
            $out[] = round(((float) $p) * 100.0, 2); // m → cm
        }

        return $out;
    }
}
