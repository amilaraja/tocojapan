<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        // ~30 sample vehicles for listing UI testing.
        Vehicle::factory()->count(30)->create();
    }
}
