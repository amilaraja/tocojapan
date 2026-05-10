<?php

namespace Database\Seeders;

use App\Models\Make;
use Illuminate\Database\Seeder;

class MakeSeeder extends Seeder
{
    public function run(): void
    {
        $makes = [
            'Toyota', 'Nissan', 'Honda', 'Mazda', 'Mitsubishi', 'Subaru', 'Suzuki',
            'Daihatsu', 'Isuzu', 'Lexus', 'Hino', 'UD Trucks', 'Acura', 'Infiniti',
            'BMW', 'Mercedes-Benz', 'Audi', 'Volkswagen', 'Volvo', 'Ford', 'Chevrolet',
            'Land Rover', 'Jaguar', 'Porsche', 'Hyundai', 'Kia', 'Peugeot', 'Renault',
            'Mini', 'Tesla',
        ];

        foreach ($makes as $i => $name) {
            Make::updateOrCreate(
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
