<?php

namespace App\Listeners;

use App\Models\Vehicle;
use App\Services\ImageProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ConvertVehiclePhotoOnUpload
{
    public function __construct(protected ImageProcessor $processor) {}

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        if ($media->model_type !== Vehicle::class) {
            return;
        }
        if ($media->collection_name !== 'photos') {
            return;
        }
        if (! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }
        if ($media->mime_type === 'image/svg+xml') {
            // Skip vector — no point rasterising.
            return;
        }

        try {
            $this->convert($media);
        } catch (\Throwable $e) {
            Log::error('Vehicle photo conversion failed', [
                'media_id' => $media->id,
                'file' => $media->file_name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function convert(Media $media): void
    {
        $disk = Storage::disk($media->disk);
        $originalRelative = $media->id.'/'.$media->file_name;
        if (! $disk->exists($originalRelative)) {
            return;
        }

        $originalAbsolute = $disk->path($originalRelative);
        $newFileName = pathinfo($media->file_name, PATHINFO_FILENAME).'.webp';
        $newRelative = $media->id.'/'.$newFileName;
        $newAbsolute = $disk->path($newRelative);

        if ($newAbsolute === $originalAbsolute && $media->mime_type === 'image/webp') {
            // Already a webp at the right path — still resize/watermark in place.
            $tmp = $originalAbsolute.'.tmp';
            $this->processor->process($originalAbsolute, $tmp);
            @rename($tmp, $originalAbsolute);
            $media->size = filesize($originalAbsolute) ?: $media->size;
            $media->saveQuietly();

            return;
        }

        $this->processor->process($originalAbsolute, $newAbsolute);

        if (file_exists($originalAbsolute) && $originalAbsolute !== $newAbsolute) {
            @unlink($originalAbsolute);
        }

        $media->file_name = $newFileName;
        $media->name = pathinfo($newFileName, PATHINFO_FILENAME);
        $media->mime_type = 'image/webp';
        $media->size = filesize($newAbsolute) ?: 0;
        $media->saveQuietly();
    }
}
