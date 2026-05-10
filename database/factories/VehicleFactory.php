<?php

namespace Database\Factories;

use App\Models\BodyType;
use App\Models\Make;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $model = VehicleModel::inRandomOrder()->first();
        if (! $model) {
            $make = Make::firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
            $model = VehicleModel::firstOrCreate(
                ['make_id' => $make->id, 'slug' => 'corolla'],
                ['name' => 'Corolla']
            );
        }
        $make = $model->make;
        $bodyType = BodyType::inRandomOrder()->first();

        $year = $this->faker->numberBetween(2010, 2024);
        $length = $this->faker->numberBetween(380, 530);
        $width = $this->faker->numberBetween(165, 195);
        $height = $this->faker->numberBetween(140, 200);
        $m3 = round(($length * $width * $height) / 1_000_000, 4);

        $title = "{$year} {$make->name} {$model->name}";
        $ref = 'TJ-'.strtoupper($this->faker->bothify('???###'));

        return [
            'ref_no' => $ref,
            'slug' => str($title.' '.$ref)->slug(),
            'title' => $title,
            'status' => 'published',
            'make_id' => $make->id,
            'vehicle_model_id' => $model->id,
            'body_type_id' => $bodyType?->id,
            'year_first_reg' => $year,
            'mileage_km' => $this->faker->numberBetween(15_000, 180_000),
            'engine_cc' => $this->faker->randomElement([660, 1000, 1300, 1500, 1800, 2000, 2400, 2500, 3000, 3500, 4000]),
            'fuel' => $this->faker->randomElement(['petrol', 'diesel', 'hybrid', 'electric']),
            'transmission' => $this->faker->randomElement(['automatic', 'manual', 'cvt']),
            'drive' => $this->faker->randomElement(['2wd', '4wd', 'awd']),
            'steering_side' => 'right',
            'exterior_color' => $this->faker->randomElement(['White', 'Black', 'Silver', 'Pearl White', 'Blue', 'Red', 'Grey']),
            'interior_color' => $this->faker->randomElement(['Black', 'Grey', 'Beige']),
            'doors' => $this->faker->randomElement([2, 4, 5]),
            'seats' => $this->faker->randomElement([2, 4, 5, 7, 8]),
            'length_cm' => $length,
            'width_cm' => $width,
            'height_cm' => $height,
            'm3' => $m3,
            'price_fob' => $this->faker->numberBetween(2_000, 35_000),
            'currency' => 'USD',
            'price_on_request' => false,
            'warranty_period' => '3 months',
            'features' => [
                'comfort' => $this->faker->randomElements(['Air Con', 'Cruise Control', 'Power Steering', 'Power Windows', 'Power Mirrors', 'Keyless Entry', 'Push Start'], 4),
                'safety' => $this->faker->randomElements(['ABS', 'Airbags', 'Traction Control', 'Reverse Camera', 'Parking Sensors'], 3),
            ],
            'description' => $this->faker->paragraph(3),
            'published_at' => now()->subDays($this->faker->numberBetween(0, 30)),
        ];
    }
}
