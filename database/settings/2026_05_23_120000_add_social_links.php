<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('social.links', [
            ['platform' => 'facebook', 'label' => 'Facebook', 'url' => ''],
            ['platform' => 'tiktok', 'label' => 'TikTok', 'url' => ''],
            ['platform' => 'instagram', 'label' => 'Instagram', 'url' => ''],
            ['platform' => 'youtube', 'label' => 'YouTube', 'url' => ''],
            ['platform' => 'linkedin', 'label' => 'LinkedIn', 'url' => ''],
        ]);
    }

    public function down(): void
    {
        $this->migrator->delete('social.links');
    }
};
