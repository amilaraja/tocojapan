<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Port;
use Illuminate\Database\Seeder;

class PortSeeder extends Seeder
{
    public function run(): void
    {
        // Seed values are placeholders; admin will tune rate_per_m3 in Filament.
        $ports = [
            'LK' => [['name' => 'Colombo', 'unlocode' => 'LKCMB', 'rate' => 25.00]],
            'TZ' => [['name' => 'Dar es Salaam', 'unlocode' => 'TZDAR', 'rate' => 32.00]],
            'KE' => [['name' => 'Mombasa', 'unlocode' => 'KEMBA', 'rate' => 33.00]],
            'UG' => [['name' => 'Mombasa (Kampala via)', 'unlocode' => 'KEMBA', 'rate' => 38.00]],
            'ZM' => [['name' => 'Dar es Salaam (Lusaka via)', 'unlocode' => 'TZDAR', 'rate' => 40.00]],
            'ZW' => [['name' => 'Durban (Harare via)', 'unlocode' => 'ZADUR', 'rate' => 45.00]],
            'MZ' => [['name' => 'Maputo', 'unlocode' => 'MZMPM', 'rate' => 38.00]],
            'MW' => [['name' => 'Dar es Salaam (Lilongwe via)', 'unlocode' => 'TZDAR', 'rate' => 42.00]],
            'BW' => [['name' => 'Walvis Bay (Gaborone via)', 'unlocode' => 'NAWVB', 'rate' => 48.00]],
            'NA' => [['name' => 'Walvis Bay', 'unlocode' => 'NAWVB', 'rate' => 36.00]],
            'CD' => [['name' => 'Matadi', 'unlocode' => 'CDMTD', 'rate' => 55.00]],
            'RW' => [['name' => 'Mombasa (Kigali via)', 'unlocode' => 'KEMBA', 'rate' => 50.00]],
            'BI' => [['name' => 'Dar es Salaam (Bujumbura via)', 'unlocode' => 'TZDAR', 'rate' => 52.00]],
            'GY' => [['name' => 'Georgetown', 'unlocode' => 'GYGEO', 'rate' => 55.00]],
            'TT' => [['name' => 'Port of Spain', 'unlocode' => 'TTPOS', 'rate' => 50.00]],
            'JM' => [['name' => 'Kingston', 'unlocode' => 'JMKIN', 'rate' => 52.00]],
            'BS' => [['name' => 'Nassau', 'unlocode' => 'BSNAS', 'rate' => 56.00]],
            'MU' => [['name' => 'Port Louis', 'unlocode' => 'MUPLU', 'rate' => 30.00]],
            'MG' => [['name' => 'Toamasina', 'unlocode' => 'MGTMM', 'rate' => 38.00]],
            'PG' => [['name' => 'Port Moresby', 'unlocode' => 'PGPOM', 'rate' => 60.00]],
            'NZ' => [
                ['name' => 'Auckland', 'unlocode' => 'NZAKL', 'rate' => 42.00],
                ['name' => 'Wellington', 'unlocode' => 'NZWLG', 'rate' => 44.00],
            ],
            'GB' => [
                ['name' => 'Southampton', 'unlocode' => 'GBSOU', 'rate' => 50.00],
                ['name' => 'Felixstowe', 'unlocode' => 'GBFXT', 'rate' => 50.00],
            ],
            'IE' => [['name' => 'Dublin', 'unlocode' => 'IEDUB', 'rate' => 55.00]],
        ];

        foreach ($ports as $iso2 => $list) {
            $country = Country::where('iso2', $iso2)->first();
            if (! $country) {
                continue;
            }

            foreach ($list as $i => $p) {
                Port::updateOrCreate(
                    ['country_id' => $country->id, 'slug' => str($p['name'])->slug()],
                    [
                        'name' => $p['name'],
                        'unlocode' => $p['unlocode'],
                        'rate_per_m3' => $p['rate'],
                        'is_active' => true,
                        'sort_order' => $i,
                    ]
                );
            }
        }
    }
}
