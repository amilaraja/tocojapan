<?php

use App\Models\Vehicle;
use App\Modules\PartnerApi\Domain\Images\ImageToken;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\PartnerApi\Support\Partners;

/*
 * Signed images (§9, FR-IMG-01..03), limits (§8.3), usage (FR-USE-01, §8.6).
 * Media and storage are redirected to a temp folder: test media ids must
 * never touch the live storage/app/public or private folders.
 */

beforeEach(function () {
    config(['partner_api.enabled' => true]);
    $this->tmpStorage = sys_get_temp_dir().'/papi-storage-'.bin2hex(random_bytes(4));
    mkdir($this->tmpStorage.'/app/private', 0777, true);
    $this->app->useStoragePath($this->tmpStorage);
    Storage::fake('public');
});

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->tmpStorage));

function carWithPhoto(): Vehicle
{
    $car = Vehicle::factory()->create(['status' => 'published', 'stock_no' => 'E0'.random_int(3000, 9999), 'price_fob' => 9000]);
    $car->addMedia(UploadedFile::fake()->image('front.jpg', 1600, 1200))->toMediaCollection('photos');

    return $car->fresh();
}

function photoUrlFor(Vehicle $car, ?\App\Modules\PartnerApi\Models\Partner $partner = null, ?\App\Modules\PartnerApi\Models\PartnerClient $client = null): array
{
    $client ??= Partners::client($partner ?? Partners::make())[0];
    $token = Partners::token($client);
    $photo = test()->withToken($token)->getJson(Partners::url('vehicles/'.$car->stock_no))->assertOk()->json('data.photos.0');

    return [$photo, $client, $token];
}

function imagePath(string $url): string
{
    return parse_url($url, PHP_URL_PATH);
}

it('keeps a private clean master of every new upload (FR-IMG-01)', function () {
    $car = carWithPhoto();
    $media = $car->getFirstMedia('photos');

    $masters = glob($this->tmpStorage.'/app/private/media-masters/'.$media->id.'/*');
    expect($masters)->toHaveCount(1)->and(basename($masters[0]))->toBe('front.jpg');
});

it('serves a signed photo, capped at the partner width, never cached on the way (§9.2)', function () {
    $car = carWithPhoto();
    [$photo] = photoUrlFor($car, Partners::make(['image_max_width' => 640]));

    expect($photo['width'])->toBe(640)->and($photo['id'])->toStartWith('ph_')
        ->and($photo['url'])->toContain('/partner/v1/img/');

    $response = $this->get(imagePath($photo['url']))->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $size = getimagesize($response->baseResponse->getFile()->getPathname());
    expect($size[0])->toBe(640);

    expect(PartnerRequestLog::where('kind', 'image')->where('status', 200)->count())->toBe(1);
});

it('keeps photo ids stable across syncs within the hour', function () {
    $car = carWithPhoto();
    [$client] = Partners::client(Partners::make());
    [$first] = photoUrlFor($car, client: $client);
    [$second] = photoUrlFor($car, client: $client);

    expect($second['id'])->toBe($first['id'])->and($second['url'])->toBe($first['url']);
});

it('answers a bare 404 for tampered, expired, foreign or withdrawn links', function () {
    $car = carWithPhoto();
    $partner = Partners::make();
    [$photo, $client] = photoUrlFor($car, $partner);
    $path = imagePath($photo['url']);

    // Tampered signature.
    $this->get(substr($path, 0, -3).'abc')->assertNotFound()->assertJsonMissingPath('detail');

    // Expired.
    $mediaId = $car->getFirstMedia('photos')->id;
    $expired = ImageToken::make(['cid' => $client->id, 'mid' => $mediaId, 'p' => 'wm1-1280-v1', 'w' => 1280, 'exp' => time() - 1]);
    $this->get('/partner/v1/img/'.$expired)->assertNotFound();

    // Settings changed since signing: old links die (§9.4).
    $partner->update(['image_max_width' => 1024]);
    $this->get($path)->assertNotFound();
    $partner->update(['image_max_width' => 1280]);

    // Vehicle sold more than 30 days ago is out of view.
    [$fresh] = photoUrlFor($car, $partner->fresh());
    $car->update(['status' => 'sold']);
    $car->forceFill(['sold_at' => now()->subDays(40)])->saveQuietly();
    $this->get(imagePath($fresh['url']))->assertNotFound();
});

it('stops photos at once when the partner is suspended (§8.7)', function () {
    $car = carWithPhoto();
    $partner = Partners::make();
    [$photo] = photoUrlFor($car, $partner);

    $this->get(imagePath($photo['url']))->assertOk();
    $partner->suspend('Review');
    $this->get(imagePath($photo['url']))->assertNotFound();
});

it('marks Toco-hosted photos cacheable until the link expires', function () {
    $car = carWithPhoto();
    [$photo] = photoUrlFor($car, Partners::make(['image_delivery' => 'hosted']));

    expect($this->get(imagePath($photo['url']))->assertOk()->headers->get('Cache-Control'))
        ->toContain('public')->toContain('max-age=');
});

it('enforces the daily photo allowance', function () {
    $car = carWithPhoto();
    [$photo] = photoUrlFor($car, Partners::make(['image_daily_limit' => 2]));
    $path = imagePath($photo['url']);

    $this->get($path)->assertOk();
    $this->get($path)->assertOk();
    $this->get($path)->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('code', 'image_limit_reached');
});

it('limits requests per minute per key, with RateLimit headers (§8.3)', function () {
    [$client] = Partners::client(Partners::make(['rate_per_minute' => 2]));
    $token = Partners::token($client);

    $this->withToken($token)->getJson(Partners::url('me'))->assertOk()
        ->assertHeader('RateLimit-Limit', '2')->assertHeader('RateLimit-Remaining', '1')->assertHeader('X-Daily-Limit');
    $this->withToken($token)->getJson(Partners::url('me'))->assertOk()->assertHeader('RateLimit-Remaining', '0');
    $this->withToken($token)->getJson(Partners::url('me'))->assertStatus(429)
        ->assertHeader('Retry-After')->assertJsonPath('code', 'too_many_requests');
});

it('limits requests per day per partner, and 304s do not count', function () {
    Vehicle::factory()->create(['status' => 'published']);
    [$client] = Partners::client(Partners::make(['rate_per_day' => 2]));
    $token = Partners::token($client);

    $etag = $this->withToken($token)->getJson(Partners::url('vehicles'))->assertOk()->headers->get('ETag');
    foreach (range(1, 3) as $i) {
        $this->withToken($token)->withHeaders(['If-None-Match' => $etag])->getJson(Partners::url('vehicles'))->assertStatus(304);
    }
    $this->flushHeaders();
    $this->withToken($token)->getJson(Partners::url('me'))->assertOk()->assertHeader('X-Daily-Remaining', '0');
    $this->withToken($token)->getJson(Partners::url('me'))->assertStatus(429)->assertJsonPath('code', 'daily_limit_reached');
});

it('logs each call without URLs or secrets and reports usage to the partner (FR-USE-01, §8.6)', function () {
    Vehicle::factory()->create(['status' => 'published']);
    [$client] = Partners::client(Partners::make());
    $token = Partners::token($client);

    $this->withToken($token)->getJson(Partners::url('vehicles'))->assertOk();
    $this->withToken($token)->getJson(Partners::url('vehicles/NOPE'))->assertNotFound();

    $usage = $this->withToken($token)->getJson(Partners::url('usage'))->assertOk()->json('data');
    expect($usage['today']['requests'])->toBe(2)
        ->and($usage['today']['errors'])->toBe(1)
        ->and($usage['last_sync_at'])->not->toBeNull()
        ->and($usage['days'])->toHaveCount(30)
        ->and(end($usage['days'])['requests'])->toBe(2);

    $log = PartnerRequestLog::where('endpoint', 'partner.vehicles.index')->sole();
    expect($log->status)->toBe(200)->and($log->bytes)->toBeGreaterThan(0)->and($log->partner_client_id)->toBe($client->id)
        ->and(json_encode(PartnerRequestLog::all()))->not->toContain($token)->not->toContain('NOPE');
    expect($client->partner->fresh()->last_activity_at)->not->toBeNull();
});
