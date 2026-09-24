<?php

namespace App\Modules\Mailer\Domain\Campaigns;

use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/** TOC-BAN-001/002: size rules and optimisation to a JPEG under 150 KB. */
class BannerImages
{
    public const WIDTH = 1200;

    public const HEIGHT = 440;

    public const MAX_UPLOAD_KB = 1024;

    public const MAX_BYTES = 150 * 1024;

    public const DIR = 'email-assets/banners';

    /** Plain-English problem with an uploaded file, or null when it is fine. */
    public static function problem(string $file): ?string
    {
        $size = @getimagesize($file);
        if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return 'Upload a JPG or PNG image.';
        }

        [$w, $h] = $size;
        $ratio = $w / max(1, $h);
        $target = self::WIDTH / self::HEIGHT;

        if (abs($ratio - $target) / $target > 0.02 || $w < self::WIDTH * 0.98) {
            return 'The banner must be '.self::WIDTH.' × '.self::HEIGHT." pixels. This image is {$w} × {$h}.";
        }

        return null;
    }

    /**
     * Resize to exactly 1200 × 440 and compress to a JPEG ≤ 150 KB.
     *
     * @return array{path: string, width: int, height: int, bytes: int}
     */
    public function optimise(string $uploadedPath): array
    {
        $disk = Storage::disk('public');
        $source = $disk->path($uploadedPath);
        $path = self::DIR.'/'.pathinfo($uploadedPath, PATHINFO_FILENAME).'-'.substr(sha1_file($source) ?: '', 0, 10).'.jpg';
        $dest = $disk->path($path);

        if (! is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }

        foreach ([85, 78, 70, 62, 55, 48, 40] as $quality) {
            Image::load($source)->fit(Fit::Crop, self::WIDTH, self::HEIGHT)->format('jpg')->quality($quality)->save($dest);
            clearstatcache(true, $dest);
            if (filesize($dest) <= self::MAX_BYTES) {
                break;
            }
        }

        if ($uploadedPath !== $path) {
            $disk->delete($uploadedPath);
        }

        return ['path' => $path, 'width' => self::WIDTH, 'height' => self::HEIGHT, 'bytes' => (int) filesize($dest)];
    }
}
