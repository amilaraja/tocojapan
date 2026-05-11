<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PaymentSettings extends Settings
{
    public bool $paypal_enabled;

    public bool $bank_transfer_enabled;

    public string $bank_account_details;

    public static function group(): string
    {
        return 'payment';
    }
}
