<?php

namespace App\Modules\Mailer\Domain\Vehicles;

use App\Modules\Mailer\Models\EmailImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * Email-sized vehicle photos (TOC-VEH-006/007).
 *
 * Takes the vehicle's primary photo (WebP originals, which Outlook can't
 * show), centre-crops it to 540x310 and saves a JPEG under 70 KB in
 * storage/app/public/email-assets/vehicles. The file name carries a hash of
 * the source file, so a new primary photo gives a new file and old campaign
 * HTML keeps working. Served from tocojapan.com via the /storage link.
 */
class EmailImageService
{
    public const WIDTH = 540;

    public const HEIGHT = 310;

    public const MAX_BYTES = 70 * 1024;

    public const DIR = 'email-assets/vehicles';

    public const PLACEHOLDER = 'email-assets/placeholder-vehicle.jpg';

    /** JPEG qualities tried in order until the file fits MAX_BYTES. */
    protected const QUALITIES = [82, 74, 66, 58, 50, 42, 35];

    /** Public URL of the email photo, generating it on first use. */
    public function urlFor(VehicleDTO $vehicle): string
    {
        return $this->disk()->url($this->pathFor($vehicle));
    }

    /** Relative path on the public disk. Falls back to the placeholder. */
    public function pathFor(VehicleDTO $vehicle): string
    {
        $source = $vehicle->photoPath;

        if (! $source || ! is_file($source) || ! is_readable($source)) {
            return $this->placeholderPath();
        }

        $hash = $this->sourceHash($source);

        $cached = EmailImage::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('source_url_hash', $hash)
            ->first();

        if ($cached && $this->disk()->exists($cached->path)) {
            return $cached->path;
        }

        $path = self::DIR.'/'.$vehicle->id.'-'.substr($hash, 0, 12).'.jpg';

        try {
            $bytes = $this->renderJpeg($source, $this->disk()->path($path));
        } catch (Throwable $e) {
            Log::warning('Mailer: email photo could not be generated, using placeholder', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage(),
            ]);

            return $this->placeholderPath();
        }

        EmailImage::query()->updateOrCreate(
            ['vehicle_id' => $vehicle->id, 'source_url_hash' => $hash],
            ['stock_ref' => $vehicle->stockRef, 'path' => $path, 'bytes' => $bytes, 'generated_at' => now()],
        );

        return $path;
    }

    /** TOCO-branded placeholder, created once (TOC-VEH-007). */
    public function placeholderPath(): string
    {
        if (! $this->disk()->exists(self::PLACEHOLDER)) {
            $this->renderPlaceholder($this->disk()->path(self::PLACEHOLDER));
        }

        return self::PLACEHOLDER;
    }

    /** Crop + compress; returns the final size in bytes. */
    protected function renderJpeg(string $source, string $destination): int
    {
        $this->ensureDir($destination);

        foreach (self::QUALITIES as $quality) {
            Image::load($source)
                ->fit(Fit::Crop, self::WIDTH, self::HEIGHT)
                ->format('jpg')
                ->quality($quality)
                ->save($destination);

            clearstatcache(true, $destination);
            $bytes = (int) filesize($destination);

            if ($bytes <= self::MAX_BYTES) {
                return $bytes;
            }
        }

        // Still too large at the lowest quality: keep it, but record it.
        Log::warning('Mailer: email photo above 70 KB at lowest quality', ['file' => $destination, 'bytes' => $bytes]);

        return $bytes;
    }

    protected function renderPlaceholder(string $destination): void
    {
        $this->ensureDir($destination);

        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($img, 0, 0, imagecolorallocate($img, 0xFF, 0xFF, 0xFF));

        $logo = @imagecreatefrompng(dirname(__DIR__, 2).'/resources/assets/toco-logo-300.png');
        if ($logo !== false) {
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            imagecopy($img, $logo, (int) ((self::WIDTH - $lw) / 2), 100, 0, 0, $lw, $lh);
            imagedestroy($logo);
        }

        $grey = imagecolorallocate($img, 0x6B, 0x6B, 0x73);
        $text = 'PHOTO COMING SOON';
        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        if (function_exists('imagettftext') && is_file($font)) {
            $box = imagettfbbox(12, 0, $font, $text);
            $tw = abs($box[2] - $box[0]);
            imagettftext($img, 12, 0, (int) ((self::WIDTH - $tw) / 2), 215, $grey, $font, $text);
        } else {
            imagestring($img, 4, (int) ((self::WIDTH - strlen($text) * 8) / 2), 200, $text, $grey);
        }

        // TOCO red rule along the bottom.
        imagefilledrectangle($img, 0, self::HEIGHT - 6, self::WIDTH, self::HEIGHT, imagecolorallocate($img, 0xE3, 0x06, 0x13));

        imagejpeg($img, $destination, 82);
        imagedestroy($img);
    }

    protected function sourceHash(string $source): string
    {
        return sha1($source.'|'.filesize($source).'|'.filemtime($source));
    }

    protected function ensureDir(string $file): void
    {
        $dir = dirname($file);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    protected function disk(): \Illuminate\Contracts\Filesystem\Filesystem|\Illuminate\Filesystem\FilesystemAdapter
    {
        return Storage::disk('public');
    }
}
