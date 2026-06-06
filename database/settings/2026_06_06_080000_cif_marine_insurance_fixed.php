<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Marine insurance is now a flat USD amount instead of a percentage
        // of (FOB + freight). Default $35 per shipment.
        $this->migrator->add('cif.marine_insurance_usd', 35.00);

        // Retire the legacy percentage. The port-level override column stays
        // in the schema (inert) so historical orders still cast cleanly.
        $this->migrator->delete('cif.insurance_pct');
    }

    public function down(): void
    {
        $this->migrator->delete('cif.marine_insurance_usd');
        $this->migrator->add('cif.insurance_pct', (float) env('TOCO_DEFAULT_INSURANCE_PCT', 0.015));
    }
};
