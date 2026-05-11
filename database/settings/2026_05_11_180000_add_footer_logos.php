<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.footer_logos', [
            ['image' => 'footer-logos/toco.png', 'alt' => 'Toco Japan', 'link' => null],
            ['image' => 'footer-logos/cert-1.webp', 'alt' => 'JUMVEA', 'link' => null],
            ['image' => 'footer-logos/cert-2.webp', 'alt' => 'JEVIC', 'link' => null],
        ]);
    }

    public function down(): void
    {
        $this->migrator->delete('general.footer_logos');
    }
};
