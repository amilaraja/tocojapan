<?php

namespace App\Console\Commands\Migration;

use App\Models\BodyType;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('migrate:fix-vehicle-models {--dry-run} {--prune-orphans : Delete vehicle_models rows with no vehicles after re-linking}')]
#[Description('Re-link vehicles to the REAL model post (Vitz/Prius/Raize…) + body type, by re-reading WP postmeta.')]
class FixVehicleModelsFromWp extends Command
{
    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $prune = (bool) $this->option('prune-orphans');

        $vehicles = Vehicle::query()->orderBy('id')->get();
        $this->info("Re-linking {$vehicles->count()} vehicles...");

        $bar = $this->output->createProgressBar($vehicles->count());
        $bar->start();

        $stats = [
            'updated' => 0,
            'unchanged' => 0,
            'no_wp_id' => 0,
            'no_meta' => 0,
            'no_make' => 0,
            'errors' => 0,
        ];
        $errors = [];

        foreach ($vehicles as $vehicle) {
            try {
                $wpPostId = $this->extractWpPostId($vehicle);
                if (! $wpPostId) {
                    $stats['no_wp_id']++;
                    $bar->advance();

                    continue;
                }

                $meta = DB::connection('wp')->table('postmeta')
                    ->where('post_id', $wpPostId)
                    ->pluck('meta_value', 'meta_key');

                $modelPostId = $this->unserializePostId($meta['vmodel'] ?? null);
                $makePostId = $this->unserializePostId($meta['vmake'] ?? null);

                if (! $modelPostId && ! $makePostId) {
                    $stats['no_meta']++;
                    $bar->advance();

                    continue;
                }

                $modelName = null;
                if ($modelPostId) {
                    $modelName = DB::connection('wp')->table('posts')
                        ->where('ID', $modelPostId)
                        ->value('post_title');
                }

                $bodyTypeId = $this->resolveBodyType($wpPostId);

                // Find/create real model under the vehicle's existing make.
                $newModelId = $vehicle->vehicle_model_id;
                if ($modelName && $vehicle->make_id) {
                    $cleanName = trim($modelName);
                    $slug = Str::slug($vehicle->make->slug.'-'.$cleanName);

                    $model = VehicleModel::query()->where(function ($q) use ($vehicle, $slug, $cleanName) {
                        $q->where(function ($qq) use ($vehicle, $slug) {
                            $qq->where('make_id', $vehicle->make_id)->where('slug', $slug);
                        })->orWhere(function ($qq) use ($vehicle, $cleanName) {
                            $qq->where('make_id', $vehicle->make_id)->whereRaw('LOWER(name) = ?', [strtolower($cleanName)]);
                        });
                    })->first();

                    if (! $model && ! $dry) {
                        $model = VehicleModel::create([
                            'make_id' => $vehicle->make_id,
                            'name' => $cleanName,
                            'slug' => $slug,
                            'is_active' => true,
                        ]);
                    }
                    $newModelId = $model?->id ?? $vehicle->vehicle_model_id;
                }

                $changed = $newModelId !== $vehicle->vehicle_model_id || $bodyTypeId !== $vehicle->body_type_id;

                if ($changed) {
                    if (! $dry) {
                        $vehicle->vehicle_model_id = $newModelId;
                        $vehicle->body_type_id = $bodyTypeId;
                        $vehicle->saveQuietly();
                    }
                    $stats['updated']++;
                } else {
                    $stats['unchanged']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $errors[] = "vehicle #{$vehicle->id} ({$vehicle->ref_no}): ".$e->getMessage();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        foreach ($stats as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        if ($errors) {
            $this->newLine();
            $this->warn('First 10 errors:');
            foreach (array_slice($errors, 0, 10) as $err) {
                $this->line('  '.$err);
            }
        }

        if ($prune && ! $dry) {
            $usedIds = Vehicle::query()->whereNotNull('vehicle_model_id')->pluck('vehicle_model_id')->unique()->all();
            $deleted = VehicleModel::query()->whereNotIn('id', $usedIds)->delete();
            $this->info("Pruned {$deleted} orphan vehicle_models rows.");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Vehicle slugs and ref_no values both end with -{wp_post_id} from the
     * original import — pull it back out.
     */
    protected function extractWpPostId(Vehicle $v): ?int
    {
        foreach ([$v->slug, $v->ref_no] as $candidate) {
            if (! $candidate) {
                continue;
            }
            if (preg_match('/-(\d{3,})$/', $candidate, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    protected function unserializePostId(mixed $raw): ?int
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = @unserialize($raw);
        if (! is_array($decoded) || empty($decoded)) {
            return null;
        }
        $first = reset($decoded);

        return is_numeric($first) ? (int) $first : null;
    }

    /**
     * Look at this vehicle's WP product_cat children (sedan/suv/wagon etc.) and
     * map to a Toco body_types row by slug.
     */
    protected function resolveBodyType(int $wpPostId): ?int
    {
        $cats = DB::connection('wp')->table('term_relationships as tr')
            ->join('term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tr.object_id', $wpPostId)
            ->where('tt.taxonomy', 'product_cat')
            ->where('tt.parent', '!=', 0)
            ->select('t.slug', 't.name')
            ->get();

        // Their slugs are "{type}-{make}", e.g. "sedan-toyota". Strip the
        // trailing -make to match toco body_types.slug.
        $map = [
            'sedan' => 'sedan', 'hatchback' => 'hatchback', 'suv' => 'suv',
            'wagon' => 'wagon', 'coupe' => 'coupe', 'van' => 'van',
            'mini-van' => 'mini-van', 'minivan' => 'mini-van',
            'mini-vehicle' => 'mini-van', 'truck' => 'truck',
            'mini-truck' => 'mini-truck', 'mini-bus' => 'mini-bus',
            'bus' => 'bus', 'pick-up' => 'pickup', 'pickup' => 'pickup',
            'convertible' => 'convertible', 'machinery' => 'heavy-equipment',
            'motorcycle' => 'motorcycle',
        ];

        foreach ($cats as $cat) {
            $base = preg_replace('/-[a-z0-9]+$/', '', $cat->slug);
            if (isset($map[$base])) {
                return BodyType::where('slug', $map[$base])->value('id');
            }
        }

        return null;
    }
}
