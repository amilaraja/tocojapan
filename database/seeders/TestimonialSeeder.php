<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'K. Muzinga', 'country' => 'Congo',       'flag' => '🇨🇩', 'quote' => 'Toyota Vanguard arrived exactly as promised — paperwork was sorted before the vessel docked.', 'image' => 'img/v5/testimonial-1.jpg'],
            ['name' => 'Marcus O.',  'country' => 'Jamaica',     'flag' => '🇯🇲', 'quote' => 'Hilux Surf, second order. FOB pricing was honest and the auction sheet translation was clear.',     'image' => 'img/v5/car-2.jpg'],
            ['name' => 'Aroha T.',   'country' => 'New Zealand', 'flag' => '🇳🇿', 'quote' => 'Compliance docs handled end-to-end. Cleared NZ customs in a week.',                                 'image' => 'img/v5/car-4.jpg'],
            ['name' => 'Daniel K.',  'country' => 'Kenya',       'flag' => '🇰🇪', 'quote' => 'Live RoRo tracking meant I knew exactly when the vessel would dock in Mombasa.',                    'image' => 'img/v5/car-3.jpg'],
            ['name' => 'Sione F.',   'country' => 'Fiji',        'flag' => '🇫🇯', 'quote' => 'Inspection report was detailed. No surprises on arrival, the car was spotless.',                   'image' => 'img/v5/car-1.jpg'],
            ['name' => 'Adaeze N.',  'country' => 'Nigeria',     'flag' => '🇳🇬', 'quote' => 'Smooth Lagos delivery. Toco handled the duties paperwork I was dreading.',                          'image' => 'img/v5/car-2.jpg'],
        ];

        foreach ($items as $i => $row) {
            $imagePath = $row['image'];
            unset($row['image']);

            $t = Testimonial::updateOrCreate(
                ['name' => $row['name'], 'country' => $row['country']],
                array_merge($row, [
                    'stars' => 5,
                    'is_featured' => true,
                    'is_published' => true,
                    'sort_order' => ($i + 1) * 10,
                ])
            );

            $absolute = public_path($imagePath);
            if (! $t->hasMedia('photo') && file_exists($absolute)) {
                try {
                    $t->addMedia($absolute)->preservingOriginal()->toMediaCollection('photo');
                } catch (FileDoesNotExist $e) {
                    // ignore — admin can upload via Filament
                }
            }
        }
    }
}
