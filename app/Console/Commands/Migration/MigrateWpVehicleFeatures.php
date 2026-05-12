<?php

namespace App\Console\Commands\Migration;

use App\Models\Vehicle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('migrate:wp-features
    {--vehicle= : Only one vehicle id}
    {--dry-run}')]
#[Description('Pull individual {group}_{option} postmeta flags from WP and store them on vehicles.features.')]
class MigrateWpVehicleFeatures extends Command
{
    public function handle(): int
    {
        $schema = config('vehicle_features');
        $dry = (bool) $this->option('dry-run');

        $query = Vehicle::query()->orderBy('id');
        if ($id = $this->option('vehicle')) {
            $query->where('id', $id);
        }

        $stats = ['matched' => 0, 'no_wp_id' => 0, 'updated' => 0, 'unchanged' => 0];

        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->each(function (Vehicle $v) use ($schema, $dry, &$stats, $bar) {
            $wpId = $this->extractWpPostId($v);
            if (! $wpId) {
                $stats['no_wp_id']++;
                $bar->advance();

                return;
            }

            // Build the list of postmeta keys we want, then fetch them all in one go.
            $wanted = [];
            foreach ($schema as $groupKey => $group) {
                foreach ($group['options'] as $opt) {
                    $wanted[] = $groupKey.'_'.$opt['key'];
                }
            }

            $rows = DB::connection('wp')->table('postmeta')
                ->where('post_id', $wpId)
                ->whereIn('meta_key', $wanted)
                ->pluck('meta_value', 'meta_key');

            $features = [];
            foreach ($schema as $groupKey => $group) {
                $bucket = [];
                foreach ($group['options'] as $opt) {
                    $val = $rows[$groupKey.'_'.$opt['key']] ?? null;
                    if ($val !== null && $this->isTruthy($val)) {
                        $bucket[$opt['key']] = $opt['label'];
                    }
                }
                if ($bucket) {
                    $features[$groupKey] = $bucket;
                }
            }

            if ($features === ($v->features ?? [])) {
                $stats['unchanged']++;
            } else {
                if (! $dry) {
                    $v->features = $features ?: null;
                    $v->saveQuietly();
                }
                $stats['updated']++;
            }

            $stats['matched']++;
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);
        foreach ($stats as $k => $v) {
            $this->line('  '.$k.': '.$v);
        }

        return self::SUCCESS;
    }

    protected function extractWpPostId(Vehicle $v): ?int
    {
        foreach ([$v->slug, $v->ref_no] as $candidate) {
            if ($candidate && preg_match('/-(\d{3,})$/', $candidate, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    protected function isTruthy(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'yes', 'true', 'on'], true);
    }
}
