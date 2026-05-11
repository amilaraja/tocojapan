<?php

namespace App\Services;

use App\Settings\ImageSettings;
use Spatie\Image\Enums\AlignPosition;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\Unit;
use Spatie\Image\Image;

class ImageProcessor
{
    public function __construct(protected ImageSettings $settings) {}

    /**
     * Resize + watermark + write the image to $destination as WebP.
     * Caller is responsible for moving/deleting the source if needed.
     */
    public function process(string $source, string $destination): void
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

        $image->format('webp')->quality($this->settings->webp_quality)->save($destination);
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
