<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ReprocessVehicleImages extends Command
{
    protected $signature = 'vehicles:reprocess-images
        {--vehicle= : Reprocess just one vehicle by ID}
        {--dry-run : Show what would change without writing files}';

    protected $description = 'Resize, watermark, and convert every vehicle photo to WebP using current image settings.';

    public function handle(ImageProcessor $processor): int
    {
        $query = Media::query()
            ->where('model_type', Vehicle::class)
            ->where('collection_name', 'photos')
            ->where('mime_type', 'like', 'image/%')
            ->where('mime_type', '!=', 'image/svg+xml');

        if ($id = $this->option('vehicle')) {
            $query->where('model_id', $id);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No vehicle photos to reprocess.');

            return self::SUCCESS;
        }

        $this->info("Reprocessing {$total} photo(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $dry = (bool) $this->option('dry-run');
        $converted = $skipped = $errors = 0;

        $query->orderBy('id')->chunkById(50, function ($photos) use ($processor, &$converted, &$skipped, &$errors, $bar, $dry) {
            foreach ($photos as $media) {
                try {
                    if ($dry) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }

                    if ($this->convertOne($processor, $media)) {
                        $converted++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("  #{$media->id} {$media->file_name}: ".$e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(($dry ? '[dry-run] ' : '')."Done. Converted: {$converted}  Skipped: {$skipped}  Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function convertOne(ImageProcessor $processor, Media $media): bool
    {
        $disk = Storage::disk($media->disk);
        $sourceRel = $media->id.'/'.$media->file_name;
        if (! $disk->exists($sourceRel)) {
            return false;
        }

        $sourceAbs = $disk->path($sourceRel);
        $newFileName = pathinfo($media->file_name, PATHINFO_FILENAME).'.webp';
        $newRel = $media->id.'/'.$newFileName;
        $newAbs = $disk->path($newRel);

        $isSamePath = $sourceAbs === $newAbs;
        $tmp = $sourceAbs.'.tmp';

        $processor->process($sourceAbs, $isSamePath ? $tmp : $newAbs);

        if ($isSamePath) {
            @rename($tmp, $sourceAbs);
        } elseif (file_exists($sourceAbs)) {
            @unlink($sourceAbs);
        }

        $media->file_name = $newFileName;
        $media->name = pathinfo($newFileName, PATHINFO_FILENAME);
        $media->mime_type = 'image/webp';
        $media->size = filesize($isSamePath ? $sourceAbs : $newAbs) ?: $media->size;
        $media->saveQuietly();

        return true;
    }
}
