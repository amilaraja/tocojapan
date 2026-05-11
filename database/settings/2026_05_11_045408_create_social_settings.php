<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('social.facebook_enabled', false);
        $this->migrator->add('social.facebook_page_id', null);
        $this->migrator->addEncrypted('social.facebook_page_access_token', null);
        $this->migrator->add('social.facebook_post_template',
            "🚗 {title}\n\n"
            ."Year: {year} · Mileage: {mileage} km · Engine: {engine_cc}cc\n"
            ."FOB: {price}\n\n"
            ."View details: {url}\n\n"
            ."#JapaneseCars #TocoJapan #UsedCars"
        );
    }

    public function down(): void
    {
        $this->migrator->delete('social.facebook_enabled');
        $this->migrator->delete('social.facebook_page_id');
        $this->migrator->delete('social.facebook_page_access_token');
        $this->migrator->delete('social.facebook_post_template');
    }
};
