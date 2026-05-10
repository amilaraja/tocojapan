<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CountrySeeder::class,
            PortSeeder::class,
            MakeSeeder::class,
            VehicleModelSeeder::class,
            BodyTypeSeeder::class,
            AdminUserSeeder::class,
            VehicleSeeder::class,
            PageSeeder::class,
        ]);
    }
}
