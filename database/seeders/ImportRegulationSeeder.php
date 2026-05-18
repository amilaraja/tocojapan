<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\ImportRegulation;
use Illuminate\Database\Seeder;

class ImportRegulationSeeder extends Seeder
{
    /**
     * One baseline regulation per destination country. Figures are the
     * commonly-cited industry norms — admins should verify and adjust
     * against current law via /admin/import-regulations.
     */
    public function run(): void
    {
        $data = [
            'Sri Lanka' => ['Subject to current import policy', '14–21 days', 'Used-vehicle imports are tightly regulated and periodically restricted. Confirm the current import policy and duty structure before ordering. RHD.'],
            'Kenya' => ['Under 8 years', '25–30 days', 'Maximum 8 years from year of first registration. RHD only. Pre-export roadworthiness inspection (QISJ) is mandatory.'],
            'Tanzania' => ['No age limit', '28–32 days', 'No maximum age, but vehicles over 10 years attract higher excise duty. RHD. Pre-shipment inspection required.'],
            'Uganda' => ['No age limit', '30–40 days (via Mombasa + inland)', 'No hard age cap, but a significant environmental levy applies to vehicles over 8 years. RHD.'],
            'Zambia' => ['No age limit', '35–45 days (via Dar es Salaam + inland)', 'No age cap; higher surtax applies to older vehicles. RHD.'],
            'Zimbabwe' => ['Under 10 years (most categories)', '35–50 days (via Durban/Beira + inland)', 'Imports of vehicles older than 10 years are restricted for many categories. RHD. Confirm the current Statutory Instrument.'],
            'Malawi' => ['No age limit', '35–45 days (via Dar es Salaam/Beira + inland)', 'No age cap; duty rises with vehicle age. RHD.'],
            'Rwanda' => ['No age limit', '35–45 days (via Mombasa/Dar es Salaam + inland)', 'No age cap; older vehicles attract higher duty and environmental levy. RHD.'],
            'Burundi' => ['No age limit', '40–50 days (via Dar es Salaam + inland)', 'No age cap. RHD. Inland transit via Tanzania.'],
            'DR Congo' => ['No age limit', '35–45 days', 'No age cap. RHD. Entry via Matadi, or via Dar es Salaam for the east.'],
            'Madagascar' => ['No age limit', '25–30 days', 'No age cap. RHD. Port: Toamasina.'],
            'Mauritius' => ['Under 4 years', '20–25 days', 'Cars must be under 4 years old at import. RHD. Pre-shipment inspection required.'],
            'Mozambique' => ['No age limit', '28–35 days', 'No age cap; duty increases with vehicle age. RHD.'],
            'Namibia' => ['No age limit', '30–40 days', 'No age cap for Namibia-registered use. RHD. Note SACU resale restrictions.'],
            'Botswana' => ['No age limit', '35–45 days (via Walvis Bay/Durban + inland)', 'No age cap. RHD.'],
            'Bahamas' => ['Under 10 years (recommended)', '35–45 days', 'Older vehicles attract substantially higher duty. RHD permitted. Port: Nassau.'],
            'Guyana' => ['Under 8 years', '38–48 days', 'Cars typically must be under 8 years old. RHD. Port: Georgetown.'],
            'Jamaica' => ['Cars under 5 years; commercial under 10 years', '35–45 days', 'Strict age limits — motor cars max 5 years, light commercials max 10 years. Import licence required. RHD.'],
            'Trinidad and Tobago' => ['Cars under 3 years', '38–48 days', 'Foreign-used cars must generally be under 3 years old; an import permit is required. RHD.'],
            'Ireland' => ['No age limit', '35–45 days', 'No age cap. VRT and CO2 charges apply on registration. RHD — Japanese vehicles suit Ireland.'],
            'United Kingdom' => ['No age limit', '35–45 days', 'No age cap. IVA test and registration required. RHD.'],
            'New Zealand' => ['No age limit', '12–18 days', 'No age cap, but emissions and frontal-impact standards plus entry certification apply. RHD.'],
            'Papua New Guinea' => ['No age limit', '14–21 days', 'No hard age cap. RHD.'],
        ];

        foreach ($data as $countryName => [$years, $shipment, $comments]) {
            $country = Country::where('name', $countryName)->first();
            if (! $country) {
                $this->command?->warn("Country not found: {$countryName}");

                continue;
            }

            $regulation = ImportRegulation::updateOrCreate(
                ['country_id' => $country->id],
                [
                    'year_restriction' => $years,
                    'time_of_shipment' => $shipment,
                    'comments' => $comments,
                    'is_active' => true,
                ]
            );

            $regulation->ports()->sync($country->ports()->pluck('id')->all());
        }

        $this->command?->info('Seeded import regulations for '.count($data).' countries.');
    }
}
