<?php

namespace App\Modules\PartnerApi\Domain\Images;

use Illuminate\Support\Facades\File;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Partner image variants (SRS §9.3), rendered on first request and cached
 * privately per (media, profile), so partners on the same profile share one
 * file. Phase 1 source: the watermarked 1280 px "gallery" file, resized
 * down to the partner's maximum width. Never web-served directly.
 */
class ImageVariants
{
    /** Absolute path of the variant, rendering it when missing. Null when the photo has no file. */
    public static function path(Media $media, string $profile, int $width): ?string
    {
        $dir = storage_path('app/'.config('partner_api.images.variant_disk_path').'/'.$media->id);
        $target = $dir.'/'.substr(hash('sha256', $profile.'|'.$width), 0, 24).'.webp';

        if (is_file($target)) {
            return $target;
        }

        $source = self::source($media);
        if ($source === null) {
            return null;
        }

        File::ensureDirectoryExists($dir, 0750);
        $tmp = $target.'.'.bin2hex(random_bytes(4)).'.tmp';

        if ($width >= PartnerImages::SOURCE_WIDTH && str_ends_with($source, '.webp')) {
            copy($source, $tmp);
        } else {
            Image::load($source)->width($width)->format('webp')->quality(80)->save($tmp);
        }

        // Atomic: a concurrent request sees either nothing or the whole file.
        rename($tmp, $target);

        return $target;
    }

    /** Drop every cached variant of a photo (profile change, revocation, photo deleted). */
    public static function forget(int $mediaId): void
    {
        File::deleteDirectory(storage_path('app/'.config('partner_api.images.variant_disk_path').'/'.$mediaId));
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
