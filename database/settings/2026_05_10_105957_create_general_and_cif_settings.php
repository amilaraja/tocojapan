<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'Toco Japan');
        $this->migrator->add('general.contact_email', 'sales@tocojapan.com');
        $this->migrator->add('general.contact_phone', null);
        $this->migrator->add('general.whatsapp_number', null);

        $this->migrator->add('cif.insurance_pct', (float) env('TOCO_DEFAULT_INSURANCE_PCT', 0.015));
        $this->migrator->add('cif.default_currency', env('TOCO_DEFAULT_CURRENCY', 'USD'));
        $this->migrator->add('cif.price_on_request_default', false);
    }
};
