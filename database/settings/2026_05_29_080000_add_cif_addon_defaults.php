<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('cif.maintenance_package_usd', 195.00);
        $this->migrator->add('cif.pre_inspection_fee_usd', 500.00);
    }

    public function down(): void
    {
        $this->migrator->delete('cif.maintenance_package_usd');
        $this->migrator->delete('cif.pre_inspection_fee_usd');
    }
};
