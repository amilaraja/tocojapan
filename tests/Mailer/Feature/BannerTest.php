<?php

use App\Models\User;
use App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder;
use App\Modules\Mailer\Domain\Campaigns\BannerImages;
use App\Modules\Mailer\Filament\Resources\Banners\Pages\CreateBanner;
use App\Modules\Mailer\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Modules\Mailer\Models\Banner;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Support\MailerAccess;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Storage::fake('public');
    $this->seed(MailerPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(MailerAccess::ROLE_MARKETER);
    $this->actingAs($user);
});

function bannerUpload(int $w, int $h, string $name = 'banner.jpg'): UploadedFile
{
    $img = imagecreatetruecolor($w, $h);
    for ($i = 0; $i < 3000; $i++) {
        imagefilledellipse($img, random_int(0, $w), random_int(0, $h), random_int(10, 90), random_int(10, 90), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    }
    ob_start();
    imagejpeg($img, null, 95);

    return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
}

function bannerFile(int $w, int $h): string
{
    $path = tempnam(sys_get_temp_dir(), 'banner').'.jpg';
    file_put_contents($path, bannerUpload($w, $h)->getContent());

    return $path;
}

it('rejects a 1200 × 600 banner with a message giving the size (TOC-BAN-001)', function () {
    Livewire::test(CreateBanner::class)
        ->fillForm(['path' => bannerUpload(1200, 600), 'name' => 'Wrong', 'alt_text' => 'x'])
        ->call('create')
        ->assertHasFormErrors(['path']);

    expect(BannerImages::problem(bannerFile(1200, 600)))
        ->toBe('The banner must be 1200 × 440 pixels. This image is 1200 × 600.')
        ->and(Banner::count())->toBe(0);
});

it('saves a 1200 × 440 banner as an optimised JPEG under 150 KB (TOC-BAN-002)', function () {
    Livewire::test(CreateBanner::class)
        ->fillForm(['path' => bannerUpload(1200, 440), 'name' => 'Hot deals', 'alt_text' => 'Hot deals from Japan', 'link_url' => 'https://tocojapan.com/vehicles'])
        ->call('create')
        ->assertHasNoFormErrors();

    $banner = Banner::sole();
    $file = Storage::disk('public')->path($banner->path);
    [$w, $h, $type] = getimagesize($file);

    expect($banner->path)->toStartWith('email-assets/banners/')->toEndWith('.jpg')
        ->and([$w, $h, $type])->toBe([1200, 440, IMAGETYPE_JPEG])
        ->and(filesize($file))->toBeLessThanOrEqual(150 * 1024)
        ->and($banner->bytes)->toBe(filesize($file))
        ->and($banner->url())->toContain('/storage/email-assets/banners/')
        ->and(Storage::disk('public')->files('email-assets/banners/uploads'))->toBe([]);
});

it('accepts a slightly different size within 2 percent', function () {
    expect(BannerImages::problem(bannerFile(1210, 440)))->toBeNull()
        ->and(BannerImages::problem(bannerFile(1200, 470)))->not->toBeNull();
});

it('hides archived banners from the picker but keeps them on old campaigns (TOC-BAN-003)', function () {
    $archived = Banner::create(['name' => 'Summer sale', 'path' => 'email-assets/banners/a.jpg', 'width' => 1200, 'height' => 440, 'bytes' => 1, 'alt_text' => 'a', 'archived_at' => now()]);
    $active = Banner::create(['name' => 'Hot deals', 'path' => 'email-assets/banners/b.jpg', 'width' => 1200, 'height' => 440, 'bytes' => 1, 'alt_text' => 'b']);

    $new = Campaign::create(['name' => 'New']);
    $old = Campaign::create(['name' => 'Old', 'banner_id' => $archived->id]);

    $options = fn (Campaign $c) => Livewire::test(EditCampaign::class, ['record' => $c->id])
        ->instance()->form->getComponent('banner_id')->getOptions();

    expect($options($new))->toBe([$active->id => 'Hot deals'])
        ->and($options($old))->toHaveKeys([$archived->id, $active->id]);

    $this->get('/admin/mailer/banners')->assertOk()->assertSee('Hot deals')->assertDontSee('Summer sale');
});
