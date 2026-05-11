<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payment.paypal_enabled', false);
        $this->migrator->add('payment.bank_transfer_enabled', false);
        $this->migrator->add('payment.bank_account_details', '');
    }

    public function down(): void
    {
        $this->migrator->delete('payment.paypal_enabled');
        $this->migrator->delete('payment.bank_transfer_enabled');
        $this->migrator->delete('payment.bank_account_details');
    }
};
