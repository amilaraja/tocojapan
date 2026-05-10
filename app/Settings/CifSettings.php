<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CifSettings extends Settings
{
    public float $insurance_pct;

    public string $default_currency;

    public bool $price_on_request_default;

    public static function group(): string
    {
        return 'cif';
    }
}
