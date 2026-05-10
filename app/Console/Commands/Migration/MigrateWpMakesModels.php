<?php

namespace App\Console\Commands\Migration;

use App\Models\Make;
use App\Models\VehicleModel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('migrate:wp-makes-models {--dry-run} {--make-taxonomy=product_cat}')]
#[Description('Import vehicle Makes (and their Models) from WordPress product_cat hierarchy.')]
class MigrateWpMakesModels extends Command
{
    public function handle(MigrationReporter $reporter): int
    {
        $dry = (bool) $this->option('dry-run');
        $tax = (string) $this->option('make-taxonomy');

        $reporter->open('makes-models');

        // WordPress: term_taxonomy.parent = 0 → top-level (Makes); parent != 0 → child (Models).
        $tops = DB::connection('wp')->table('term_taxonomy as tt')
            ->join('terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', $tax)
            ->where('tt.parent', 0)
            ->select('t.term_id', 't.name', 't.slug', 'tt.count')
            ->orderBy('t.name')
            ->get();

        $madeMakes = 0;
        $madeModels = 0;

        foreach ($tops as $top) {
            $this->line("Make: {$top->name} ({$top->slug}) — {$top->count} products");

            $makeId = -1;
            if (! $dry) {
                $make = Make::updateOrCreate(
                    ['slug' => $top->slug],
                    ['name' => $top->name, 'is_active' => true]
                );
                $makeId = $make->id;
                $madeMakes++;
            }

            $children = DB::connection('wp')->table('term_taxonomy as tt')
                ->join('terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tt.taxonomy', $tax)
                ->where('tt.parent', $top->term_id)
                ->select('t.term_id', 't.name', 't.slug')
                ->orderBy('t.name')
                ->get();

            foreach ($children as $child) {
                if (! $dry && $makeId !== -1) {
                    VehicleModel::updateOrCreate(
                        ['make_id' => $makeId, 'slug' => $child->slug],
                        ['name' => $child->name, 'is_active' => true]
                    );
                }
                $madeModels++;
            }
        }

        $reporter->note('makes_seen', $tops->count());
        $reporter->note('makes_written', $madeMakes);
        $reporter->note('models_written', $madeModels);
        $reporter->note('dry_run', $dry);
        $reporter->close('makes-models');

        $this->info(($dry ? 'DRY: ' : '')."Makes={$madeMakes}, Models={$madeModels}");

        return self::SUCCESS;
    }
}
