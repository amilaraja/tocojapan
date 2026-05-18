<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDO;

/**
 * Re-imprint every vehicle photo with the stock number.
 *
 * The stored photos already have a watermark + chassis-ref pill baked in,
 * so reprocessing them in place would double-stamp. Instead this re-runs
 * the image pipeline from the *pristine* WordPress originals kept in the
 * cutover archive, matching each photo by its filename stem.
 */
class ReimprintVehicleStock extends Command
{
    protected $signature = 'vehicles:reimprint-stock
        {--archive=/home/tocojapan.com/public_html/_wp_archive_2026-05-15/wp-content/uploads : WP uploads dir in the archive}
        {--vehicle= : Limit to one vehicle ID}
        {--dry-run : Report matches without writing files}';

    protected $description = 'Re-imprint vehicle photos with the stock number, reprocessed from the original WP images.';

    public function handle(ImageProcessor $processor): int
    {
        $archive = rtrim((string) $this->option('archive'), '/');
        if (! is_dir($archive)) {
            $this->error("Archive uploads dir not found: {$archive}");

            return self::FAILURE;
        }

        $wp = new PDO('mysql:host=127.0.0.1;dbname=toco_wprestore;charset=utf8mb4', 'toco_usr23', '2TJuAJ@erkE5V%6Z', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $metaStmt = $wp->prepare("SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ? AND meta_key IN ('_thumbnail_id','_product_image_gallery')");
        $fileStmt = $wp->prepare('SELECT meta_value FROM wp_postmeta WHERE post_id = ? AND meta_key = ?');

        $vehicles = Vehicle::query()
            ->whereNotNull('stock_no')
            ->when($this->option('vehicle'), fn ($q, $id) => $q->where('id', $id))
            ->with('media')
            ->get();

        $dry = (bool) $this->option('dry-run');
        $done = $missed = $errors = 0;

        $bar = $this->output->createProgressBar($vehicles->count());
        $bar->start();

        foreach ($vehicles as $vehicle) {
            if (! preg_match('/-(\d+)$/', (string) $vehicle->slug, $m)) {
                $bar->advance();

                continue;
            }
            $wpId = (int) $m[1];

            // Collect this product's attachment IDs (featured + gallery).
            $metaStmt->execute([$wpId]);
            $meta = [];
            foreach ($metaStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $meta[$row['meta_key']] = $row['meta_value'];
            }
            $attIds = array_filter(array_map('intval', array_merge(
                [$meta['_thumbnail_id'] ?? 0],
                explode(',', (string) ($meta['_product_image_gallery'] ?? '')),
            )));

            // Map each attachment's filename stem -> archive-relative path.
            $stemToPath = [];
            foreach ($attIds as $attId) {
                $fileStmt->execute([$attId, '_wp_attached_file']);
                $rel = $fileStmt->fetchColumn();
                if ($rel) {
                    $stemToPath[pathinfo($rel, PATHINFO_FILENAME)] = $rel;
                }
            }

            foreach ($vehicle->getMedia('photos') as $media) {
                if (! str_starts_with((string) $media->mime_type, 'image/') || $media->mime_type === 'image/svg+xml') {
                    continue;
                }
                $rel = $stemToPath[$media->name] ?? null;
                $orig = $rel ? $archive.'/'.ltrim($rel, '/') : null;
                if (! $orig || ! is_file($orig)) {
                    $missed++;

                    continue;
                }
                if ($dry) {
                    $done++;

                    continue;
                }
                try {
                    $disk = Storage::disk($media->disk);
                    $destAbs = $disk->path($media->id.'/'.$media->file_name);
                    $tmp = $destAbs.'.tmp';
                    $processor->process($orig, $tmp, $vehicle->stock_no);
                    @rename($tmp, $destAbs);
                    $media->size = filesize($destAbs) ?: $media->size;
                    $media->saveQuietly();
                    $done++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("  #{$media->id} {$media->file_name}: ".$e->getMessage());
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(($dry ? '[dry-run] ' : '')."Done. Re-imprinted: {$done}  Unmatched: {$missed}  Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
