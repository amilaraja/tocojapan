<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Whether to show per-make / per-body-type stock totals across the site.
        $this->migrator->add('general.show_stock_counts', false);
    }

    public function down(): void
    {
        $this->migrator->delete('general.show_stock_counts');
    }
};
