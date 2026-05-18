<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Google Analytics 4 measurement ID (G-XXXXXXXXXX). Empty = disabled.
        $this->migrator->add('general.google_analytics_id', 'G-9NBJP3F0VL');
    }

    public function down(): void
    {
        $this->migrator->delete('general.google_analytics_id');
    }
};
