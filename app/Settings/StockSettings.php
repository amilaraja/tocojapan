<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Site-wide defaults for supplier-feed stock (OnePrice …). */
class StockSettings extends Settings
{
    /** Profit margin added to supplier stock, in % of the converted supplier price. */
    public float $supplier_margin_percent;

    /** Fixed profit margin per vehicle, USD, added after the % margin. */
    public float $supplier_margin_fixed_usd;

    public static function group(): string
    {
        return 'stock';
    }
}
