<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('image.max_width', 1200);
        $this->migrator->add('image.webp_quality', 85);
        $this->migrator->add('image.watermark_enabled', false);
        $this->migrator->add('image.watermark_image_path', null);
        $this->migrator->add('image.watermark_position', 'bottom-right');
        $this->migrator->add('image.watermark_opacity', 70);
        $this->migrator->add('image.watermark_width_pct', 20);
    }

    public function down(): void
    {
        $this->migrator->delete('image.max_width');
        $this->migrator->delete('image.webp_quality');
        $this->migrator->delete('image.watermark_enabled');
        $this->migrator->delete('image.watermark_image_path');
        $this->migrator->delete('image.watermark_position');
        $this->migrator->delete('image.watermark_opacity');
        $this->migrator->delete('image.watermark_width_pct');
    }
};
