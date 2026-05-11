<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $seed = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 0],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'sort_order' => 1],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'sort_order' => 2],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'sort_order' => 3],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'sort_order' => 4],
            ['code' => 'NZD', 'name' => 'NZ Dollar', 'symbol' => 'NZ$', 'sort_order' => 5],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'sort_order' => 6],
            ['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'symbol' => 'Rs', 'sort_order' => 7],
            ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'sort_order' => 8],
            ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'sort_order' => 9],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'sort_order' => 10],
        ];

        foreach ($seed as $row) {
            Currency::updateOrCreate(['code' => $row['code']], array_merge($row, ['is_active' => true, 'rate_to_usd' => $row['code'] === 'USD' ? 1 : 1]));
        }
    }
}
