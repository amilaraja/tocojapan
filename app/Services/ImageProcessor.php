<?php

namespace App\Services;

use App\Settings\ImageSettings;
use Spatie\Image\Enums\AlignPosition;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\Unit;
use Spatie\Image\Image;

class ImageProcessor
{
    protected const FONT_PATH = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    protected const REF_FONT_SIZE = 20;

    public function __construct(protected ImageSettings $settings) {}

    /**
     * Resize + watermark + stamp ref number + write image to $destination as WebP.
     * Caller is responsible for moving/deleting the source if needed.
     */
    public function process(string $source, string $destination, ?string $refNo = null): void
    {
        $image = Image::load($source);

        $maxWidth = max(100, $this->settings->max_width);
        if ($image->getWidth() > $maxWidth) {
            $image->width($maxWidth);
        }

        if ($this->shouldWatermark()) {
            $image->watermark(
                watermarkImage: $this->absoluteWatermarkPath(),
                position: $this->resolvePosition(),
                paddingX: 2,
                paddingY: 2,
                paddingUnit: Unit::Percent,
                width: max(1, min(100, $this->settings->watermark_width_pct)),
                widthUnit: Unit::Percent,
                fit: Fit::Contain,
                alpha: max(0, min(100, $this->settings->watermark_opacity)),
            );
        }

        $refStrip = $refNo !== null && trim($refNo) !== ''
            ? $this->generateRefStrip(trim($refNo))
            : null;
        if ($refStrip !== null) {
            $image->watermark(
                watermarkImage: $refStrip,
                position: AlignPosition::BottomLeft,
                paddingX: 12,
                paddingY: 12,
                paddingUnit: Unit::Pixel,
                fit: Fit::Contain,
                alpha: 100,
            );
        }

        $image->format('webp')->quality($this->settings->webp_quality)->save($destination);

        if ($refStrip !== null && file_exists($refStrip)) {
            @unlink($refStrip);
        }
    }

    /**
     * Render the "Toco Ref# : XXXX" pill to a temp PNG with a semi-transparent
     * white background and black 20px text, then return its path.
     */
    protected function generateRefStrip(string $refNo): ?string
    {
        if (! function_exists('imagettftext') || ! is_file(self::FONT_PATH)) {
            return null;
        }

        $text = "Toco Ref# : {$refNo}";
        $font = self::FONT_PATH;
        $size = self::REF_FONT_SIZE;
        $padX = 14;
        $padY = 8;

        $bbox = imagettfbbox($size, 0, $font, $text);
        if (! is_array($bbox)) {
            return null;
        }
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);

        $w = $textWidth + $padX * 2;
        $h = $textHeight + $padY * 2;

        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        // ~65% opaque white background (alpha 0-127 where 127 = transparent).
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 45);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);

        // Black text, anti-aliased.
        $fg = imagecolorallocate($img, 0, 0, 0);
        $baselineY = $padY + $textHeight - 2; // bbox baseline offset
        imagettftext($img, $size, 0, $padX, $baselineY, $fg, $font, $text);

        $path = sys_get_temp_dir().'/ref-'.bin2hex(random_bytes(6)).'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    public function shouldWatermark(): bool
    {
        if (! $this->settings->watermark_enabled) {
            return false;
        }
        $path = $this->absoluteWatermarkPath();

        return $path !== null && is_file($path);
    }

    public function absoluteWatermarkPath(): ?string
    {
        $rel = $this->settings->watermark_image_path;
        if (empty($rel)) {
            return null;
        }

        return storage_path('app/public/'.ltrim($rel, '/'));
    }

    protected function resolvePosition(): AlignPosition
    {
        return match ($this->settings->watermark_position) {
            'top-left' => AlignPosition::TopLeft,
            'top-right' => AlignPosition::TopRight,
            'top-center' => AlignPosition::TopCenter,
            'bottom-left' => AlignPosition::BottomLeft,
            'center' => AlignPosition::Center,
            'bottom-center' => AlignPosition::BottomCenter,
            default => AlignPosition::BottomRight,
        };
    }
}
