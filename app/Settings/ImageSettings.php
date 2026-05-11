<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ImageSettings extends Settings
{
    public int $max_width;

    public int $webp_quality;

    public bool $watermark_enabled;

    public ?string $watermark_image_path;

    public string $watermark_position;

    public int $watermark_opacity;

    public int $watermark_width_pct;

    public static function group(): string
    {
        return 'image';
    }
}
