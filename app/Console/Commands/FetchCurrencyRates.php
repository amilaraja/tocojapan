<?php

namespace App\Console\Commands;

use App\Services\CurrencyRates;
use Illuminate\Console\Command;

class FetchCurrencyRates extends Command
{
    protected $signature = 'currency:fetch-rates';

    protected $description = 'Fetch USD-base FX rates from open.er-api.com and update the currencies table.';

    public function handle(CurrencyRates $rates): int
    {
        $count = $rates->fetch();
        $this->info("Updated {$count} currency rate(s).");

        return self::SUCCESS;
    }
}
