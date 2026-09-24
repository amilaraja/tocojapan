<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            \App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder::class,
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
