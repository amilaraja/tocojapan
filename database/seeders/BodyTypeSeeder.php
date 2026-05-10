<?php

namespace Database\Seeders;

use App\Models\BodyType;
use Illuminate\Database\Seeder;

class BodyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Sedan', 'Hatchback', 'SUV', 'Wagon', 'Coupe', 'Convertible',
            'Mini Van', 'Van', 'Pickup', 'Truck', 'Bus', 'Mini Truck',
            'Mini Bus', 'Heavy Equipment', 'Motorcycle',
        ];

        foreach ($types as $i => $name) {
            BodyType::updateOrCreate(
                ['slug' => str($name)->slug()],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $i,
                ]
            );
        }
    }
}
