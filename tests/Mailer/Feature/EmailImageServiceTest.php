<?php

use App\Modules\Mailer\Domain\Vehicles\EmailImageService;
use App\Modules\Mailer\Domain\Vehicles\VehicleDTO;
use App\Modules\Mailer\Models\EmailImage;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->service = new EmailImageService;
});

/** A noisy photo-like WebP, so compression has real work to do. */
function fakePhoto(int $w = 1600, int $h = 1200): string
{
    $img = imagecreatetruecolor($w, $h);
    for ($i = 0; $i < 4000; $i++) {
        $c = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
        imagefilledellipse($img, random_int(0, $w), random_int(0, $h), random_int(10, 120), random_int(10, 120), $c);
    }
    $path = tempnam(sys_get_temp_dir(), 'veh').'.webp';
    imagewebp($img, $path, 90);
    imagedestroy($img);

    return $path;
}

function dtoWithPhoto(?string $path, int $id = 42): VehicleDTO
{
    return VehicleDTO::fromArray([
        'id' => $id, 'stockRef' => 'E02056', 'title' => 'TEST', 'status' => 'available',
        'photoPath' => $path, 'url' => 'https://tocojapan.com/vehicles/test',
    ]);
}

it('creates a 540x310 JPEG under 70 KB from the primary photo (TOC-VEH-006)', function () {
    $path = $this->service->pathFor(dtoWithPhoto(fakePhoto()));

    $file = Storage::disk('public')->path($path);
    [$w, $h, $type] = getimagesize($file);

    expect($path)->toStartWith('email-assets/vehicles/42-')
        ->and($w)->toBe(540)->and($h)->toBe(310)
        ->and($type)->toBe(IMAGETYPE_JPEG)
        ->and(filesize($file))->toBeLessThanOrEqual(70 * 1024);

    expect(EmailImage::where('vehicle_id', 42)->first())
        ->stock_ref->toBe('E02056')
        ->bytes->toBe(filesize($file));
});

it('reuses the cached file for the same source photo', function () {
    $photo = fakePhoto(800, 600);

    $first = $this->service->pathFor(dtoWithPhoto($photo));
    $mtime = filemtime(Storage::disk('public')->path($first));
    $second = $this->service->pathFor(dtoWithPhoto($photo));

    expect($second)->toBe($first)
        ->and(filemtime(Storage::disk('public')->path($second)))->toBe($mtime)
        ->and(EmailImage::count())->toBe(1);
});

it('makes a new file when the primary photo changes', function () {
    $first = $this->service->pathFor(dtoWithPhoto(fakePhoto(800, 600)));
    $second = $this->service->pathFor(dtoWithPhoto(fakePhoto(900, 700)));

    expect($second)->not->toBe($first)->and(EmailImage::count())->toBe(2);
});

it('uses the TOCO placeholder when there is no photo (TOC-VEH-007)', function (?string $photo) {
    $path = $this->service->pathFor(dtoWithPhoto($photo));

    [$w, $h] = getimagesize(Storage::disk('public')->path($path));

    expect($path)->toBe(EmailImageService::PLACEHOLDER)
        ->and($w)->toBe(540)->and($h)->toBe(310)
        ->and(EmailImage::count())->toBe(0);
})->with([null, '/nonexistent/photo.webp']);

it('falls back to the placeholder when the photo cannot be read as an image', function () {
    $broken = tempnam(sys_get_temp_dir(), 'bad');
    file_put_contents($broken, 'not an image');

    expect($this->service->pathFor(dtoWithPhoto($broken)))->toBe(EmailImageService::PLACEHOLDER);
});

it('returns a public tocojapan.com URL under email-assets', function () {
    expect($this->service->urlFor(dtoWithPhoto(null)))->toContain('/storage/email-assets/');
});
