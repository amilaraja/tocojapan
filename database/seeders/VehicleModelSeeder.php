<?php

namespace Database\Seeders;

use App\Models\Make;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;

class VehicleModelSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'toyota' => ['Corolla', 'Camry', 'Vitz', 'Yaris', 'Prius', 'Aqua', 'Crown', 'Mark X', 'Allion', 'Premio', 'Wish', 'Voxy', 'Noah', 'Alphard', 'Vellfire', 'Hilux', 'Land Cruiser', 'Land Cruiser Prado', 'Harrier', 'Rav4', 'CHR', 'Hiace', 'Probox', 'Succeed', 'Vanguard'],
            'nissan' => ['March', 'Note', 'Tiida', 'Sylphy', 'Skyline', 'Fuga', 'Cube', 'Wingroad', 'AD Van', 'Serena', 'Elgrand', 'Caravan', 'Vanette', 'X-Trail', 'Juke', 'Murano', 'Patrol', 'Navara', 'Leaf', 'Dayz'],
            'honda' => ['Fit', 'Civic', 'Accord', 'Insight', 'CR-V', 'HR-V', 'Vezel', 'Stepwgn', 'Freed', 'Odyssey', 'Stream', 'Airwave', 'Partner', 'N-Box', 'N-WGN'],
            'mazda' => ['Demio', 'Axela', 'Atenza', 'Premacy', 'Biante', 'CX-3', 'CX-5', 'CX-7', 'CX-8', 'MPV', 'Bongo', 'Familia Van'],
            'mitsubishi' => ['Lancer', 'Galant', 'Pajero', 'Pajero Mini', 'Outlander', 'Delica', 'L200', 'Canter', 'Mirage', 'Colt', 'eK Wagon'],
            'subaru' => ['Impreza', 'Legacy', 'Forester', 'Outback', 'XV', 'Stella', 'Pleo', 'Sambar', 'Levorg', 'WRX'],
            'suzuki' => ['Swift', 'Solio', 'Wagon R', 'Alto', 'Every', 'Carry', 'Jimny', 'Escudo', 'Vitara', 'Hustler'],
            'daihatsu' => ['Mira', 'Move', 'Tanto', 'Hijet', 'Boon', 'Terios', 'Rocky', 'Cast'],
            'isuzu' => ['Bighorn', 'Wizard', 'D-Max', 'Forward', 'Elf', 'Giga'],
            'lexus' => ['IS', 'GS', 'LS', 'RX', 'NX', 'LX', 'CT', 'ES', 'UX', 'RC'],
            'hino' => ['Dutro', 'Ranger', 'Profia'],
        ];

        foreach ($catalog as $makeSlug => $models) {
            $make = Make::where('slug', $makeSlug)->first();
            if (! $make) {
                continue;
            }

            foreach ($models as $i => $name) {
                VehicleModel::updateOrCreate(
                    ['make_id' => $make->id, 'slug' => str($name)->slug()],
                    [
                        'name' => $name,
                        'is_active' => true,
                        'sort_order' => $i,
                    ]
                );
            }
        }
    }
}
