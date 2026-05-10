<?php

namespace App\Console\Commands\Migration;

use App\Models\Page;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('migrate:wp-pages {--dry-run} {--slugs=* : Restrict to specific WP page slugs (defaults to a curated set)}')]
#[Description('Import selected WP pages into the CMS as Default-template pages.')]
class MigrateWpPages extends Command
{
    /** @var array<int, string> */
    private const DEFAULT_SLUGS = ['about-us', 'about', 'how-it-works', 'shipping', 'inspection', 'banking', 'privacy', 'terms', 'faq'];

    public function handle(MigrationReporter $reporter): int
    {
        $dry = (bool) $this->option('dry-run');
        $slugs = $this->option('slugs') ?: self::DEFAULT_SLUGS;

        $reporter->open('pages');

        $rows = DB::connection('wp')->table('posts')
            ->where('post_type', 'page')
            ->where('post_status', 'publish')
            ->whereIn('post_name', $slugs)
            ->select('ID', 'post_name', 'post_title', 'post_content', 'post_excerpt', 'post_modified_gmt')
            ->get();

        $written = 0;

        foreach ($rows as $row) {
            $this->line("Page: /{$row->post_name} — {$row->post_title}");

            if (! $dry) {
                Page::updateOrCreate(
                    ['slug' => $row->post_name],
                    [
                        'template_key' => 'default',
                        'title' => $row->post_title,
                        'data' => [
                            'headline' => $row->post_title,
                            'kicker' => null,
                            'body' => $row->post_content,
                        ],
                        'status' => 'published',
                        'seo_description' => substr(strip_tags((string) $row->post_excerpt), 0, 480),
                        'locale' => 'en',
                        'published_at' => $row->post_modified_gmt,
                    ]
                );
                $written++;
            }
        }

        $reporter->note('seen', $rows->count());
        $reporter->note('written', $written);
        $reporter->note('slugs_requested', $slugs);
        $reporter->note('dry_run', $dry);
        $reporter->close('pages');

        $this->info(($dry ? 'DRY: ' : '')."Pages written={$written} (of {$rows->count()} matched).");

        return self::SUCCESS;
    }
}
