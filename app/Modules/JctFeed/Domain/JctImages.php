<?php

namespace App\Modules\JctFeed\Domain;

use Illuminate\Support\Facades\File;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Photos in JCT's limits (JPEG, max 1024 x 800 px, max 512 KB), rendered on
 * first request from the watermarked "gallery" file and cached privately.
 * File names carry a signature so the image folder cannot be walked by id.
 */
class JctImages
{
    /** File name JCT appends to image_base_url, e.g. "123-9f2c0a1b2c3d4e5f.jpg". */
    public static function fileName(Media $media): string
    {
        return $media->id.'-'.self::signature($media->id).'.jpg';
    }

    /** Media id from a requested file name; null when the signature does not match. */
    public static function mediaIdFrom(string $fileName): ?int
    {
        if (! preg_match('/^(\d{1,12})-([a-f0-9]{16})\.jpg$/', $fileName, $m)) {
            return null;
        }

        return hash_equals(self::signature((int) $m[1]), $m[2]) ? (int) $m[1] : null;
    }

    /** Absolute path of the JCT-sized JPEG, rendering it when missing. Null when the photo has no file. */
    public static function path(Media $media): ?string
    {
        $dir = storage_path('app/'.config('jct_feed.image_cache_path'));
        $target = $dir.'/'.$media->id.'.jpg';

        if (is_file($target)) {
            return $target;
        }

        $source = self::source($media);
        if ($source === null) {
            return null;
        }

        File::ensureDirectoryExists($dir, 0750);
        $tmp = $target.'.'.bin2hex(random_bytes(4)).'.tmp.jpg';

        // Step the quality down until the file fits JCT's size limit.
        foreach ([82, 72, 62, 50] as $quality) {
            Image::load($source)
                ->fit(Fit::Max, (int) config('jct_feed.image_max_width'), (int) config('jct_feed.image_max_height'))
                ->format('jpg')
                ->quality($quality)
                ->save($tmp);
            clearstatcache(true, $tmp);
            if (filesize($tmp) <= (int) config('jct_feed.image_max_bytes')) {
                break;
            }
        }

        // Atomic: a concurrent request sees either nothing or the whole file.
        rename($tmp, $target);

        return $target;
    }

    public static function forget(int $mediaId): void
    {
        File::delete(storage_path('app/'.config('jct_feed.image_cache_path').'/'.$mediaId.'.jpg'));
    }

    private static function signature(int $mediaId): string
    {
        return substr(hash_hmac('sha256', 'jct-photo|'.$mediaId, (string) config('app.key')), 0, 16);
    }

    private static function source(Media $media): ?string
    {
        foreach (['gallery', ''] as $conversion) {
            try {
                $path = $conversion === '' ? $media->getPath() : ($media->hasGeneratedConversion($conversion) ? $media->getPath($conversion) : null);
            } catch (\Throwable) {
                $path = null;
            }
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
