<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['iso2' => 'LK', 'name' => 'Sri Lanka', 'currency_code' => 'LKR'],
            ['iso2' => 'TZ', 'name' => 'Tanzania', 'currency_code' => 'TZS'],
            ['iso2' => 'KE', 'name' => 'Kenya', 'currency_code' => 'KES'],
            ['iso2' => 'UG', 'name' => 'Uganda', 'currency_code' => 'UGX'],
            ['iso2' => 'ZM', 'name' => 'Zambia', 'currency_code' => 'ZMW'],
            ['iso2' => 'ZW', 'name' => 'Zimbabwe', 'currency_code' => 'ZWL'],
            ['iso2' => 'MZ', 'name' => 'Mozambique', 'currency_code' => 'MZN'],
            ['iso2' => 'MW', 'name' => 'Malawi', 'currency_code' => 'MWK'],
            ['iso2' => 'BW', 'name' => 'Botswana', 'currency_code' => 'BWP'],
            ['iso2' => 'NA', 'name' => 'Namibia', 'currency_code' => 'NAD'],
            ['iso2' => 'CD', 'name' => 'DR Congo', 'currency_code' => 'CDF'],
            ['iso2' => 'RW', 'name' => 'Rwanda', 'currency_code' => 'RWF'],
            ['iso2' => 'BI', 'name' => 'Burundi', 'currency_code' => 'BIF'],
            ['iso2' => 'GY', 'name' => 'Guyana', 'currency_code' => 'GYD'],
            ['iso2' => 'TT', 'name' => 'Trinidad and Tobago', 'currency_code' => 'TTD'],
            ['iso2' => 'JM', 'name' => 'Jamaica', 'currency_code' => 'JMD'],
            ['iso2' => 'BS', 'name' => 'Bahamas', 'currency_code' => 'BSD'],
            ['iso2' => 'MU', 'name' => 'Mauritius', 'currency_code' => 'MUR'],
            ['iso2' => 'MG', 'name' => 'Madagascar', 'currency_code' => 'MGA'],
            ['iso2' => 'PG', 'name' => 'Papua New Guinea', 'currency_code' => 'PGK'],
            ['iso2' => 'NZ', 'name' => 'New Zealand', 'currency_code' => 'NZD'],
            ['iso2' => 'GB', 'name' => 'United Kingdom', 'currency_code' => 'GBP'],
            ['iso2' => 'IE', 'name' => 'Ireland', 'currency_code' => 'EUR'],
        ];

        foreach ($countries as $i => $c) {
            Country::updateOrCreate(
                ['iso2' => $c['iso2']],
                [
                    'name' => $c['name'],
                    'slug' => str($c['name'])->slug(),
                    'currency_code' => $c['currency_code'],
                    'is_active' => true,
                    'sort_order' => $i,
                ]
            );
        }
    }
}
