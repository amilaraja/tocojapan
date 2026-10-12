<?php

use App\Models\Supplier;
use App\Models\Vehicle;
use App\Modules\JctFeed\Domain\JctImages;
use App\Modules\JctFeed\Domain\JctStockFeed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
 * JCT automatic stock import feed. Media and storage go to a temp folder:
 * test media ids must never touch the live storage folders.
 */

beforeEach(function () {
    config(['jct_feed.token' => 'jct-secret-123']);
    Cache::flush();
    $this->tmpStorage = sys_get_temp_dir().'/jct-storage-'.bin2hex(random_bytes(4));
    mkdir($this->tmpStorage.'/app/private', 0777, true);
    $this->app->useStoragePath($this->tmpStorage);
    Storage::fake('public');
});

afterEach(fn () => File::deleteDirectory($this->tmpStorage));

function jctCar(array $attributes = []): Vehicle
{
    return Vehicle::factory()->create($attributes + [
        'status' => 'published',
        'stock_no' => 'E0'.random_int(3000, 9999),
        'price_fob' => 9000,
        'price_fob_discount' => null,
        'price_on_request' => false,
        'currency' => 'USD',
    ]);
}

it('answers 404 without the right token, or when no token is set', function () {
    jctCar();

    $this->get('/feeds/jct/stock.json')->assertNotFound();
    $this->get('/feeds/jct/stock.json?token=wrong')->assertNotFound();
    $this->get('/feeds/jct/stock.xml?token=wrong')->assertNotFound();

    config(['jct_feed.token' => null]);
    $this->get('/feeds/jct/stock.json?token=')->assertNotFound();
});

it('serves own stock in JCT JSON format, never cached by LiteSpeed', function () {
    $car = jctCar(['stock_no' => 'E04001', 'steering_side' => 'right', 'transmission' => 'automatic', 'drive' => '4wd', 'doors' => 5, 'description' => '<p>One owner</p>']);

    $response = $this->get('/feeds/jct/stock.json?token=jct-secret-123')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=utf-8')
        ->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');

    $json = json_decode($response->getContent(), true);
    expect($json['status'])->toBeTrue()
        ->and($json['total_vehicle'])->toBe(1)
        ->and($json['image_base_url'])->toEndWith('/feeds/jct/images/')
        ->and($json['generation_time'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/')
        ->and(array_keys($json['data']))->toBe([1]);

    $row = $json['data']['1'];
    expect($row['stock_number'])->toBe('E04001')
        ->and($row['price_usd'])->toBe('9000')
        ->and($row['status'])->toBe('Available')
        ->and($row['display'])->toBe(1)
        ->and($row['doors'])->toBe(5)
        ->and($row['drive'])->toBe('Right Hand')
        ->and($row['transmission'])->toBe('AT')
        ->and($row['wheel_drive'])->toBe('4WD')
        ->and($row['other_info'])->toBe('One owner')
        ->and($row)->not->toHaveKey('price')
        ->and($row)->not->toHaveKey('picture');
});

it('lists on-sale and under-offer own stock only, never supplier stock', function () {
    jctCar(['stock_no' => 'E04010']);
    jctCar(['stock_no' => 'E04011', 'status' => 'reserved']);
    jctCar(['stock_no' => 'E04012', 'status' => 'sold', 'sold_at' => now()]);
    jctCar(['stock_no' => 'E04013', 'status' => 'draft']);
    $supplier = Supplier::create(['name' => 'Feed supplier', 'slug' => 'jct-test-feed']);
    jctCar(['stock_no' => 'OP1', 'supplier_id' => $supplier->id]);

    $rows = collect(JctStockFeed::toArray()['data'])->keyBy('stock_number');

    expect($rows->keys()->sort()->values()->all())->toBe(['E04010', 'E04011'])
        ->and($rows['E04011']['status'])->toBe('Reserved');
});

it('sends an offer price only when it is at least US$100 below the price', function () {
    jctCar(['stock_no' => 'E04020', 'price_fob' => 9000, 'price_fob_discount' => 8500]);
    jctCar(['stock_no' => 'E04021', 'price_fob' => 9000, 'price_fob_discount' => 8950]);
    jctCar(['stock_no' => 'E04022', 'price_on_request' => true]);

    $rows = collect(JctStockFeed::toArray()['data'])->keyBy('stock_number');

    expect($rows['E04020'])->toMatchArray(['price_usd' => '9000', 'offer_price_usd' => '8500'])
        ->and($rows['E04021']['price_usd'])->toBe('8950')
        ->and($rows['E04021'])->not->toHaveKey('offer_price_usd')
        ->and($rows['E04022'])->not->toHaveKey('price_usd');
});

it('masks the chassis number unless full chassis is switched on', function () {
    jctCar(['chassis_number' => 'ZVG11-1234567']);

    expect(collect(JctStockFeed::toArray()['data'])->first()['chassis_no'])->toBe('ZVG1*-*******');

    config(['jct_feed.full_chassis' => true]);
    expect(collect(JctStockFeed::toArray()['data'])->first()['chassis_no'])->toBe('ZVG11-1234567');
});

it('serves the same cars as XML in the JCT tag layout', function () {
    $car = jctCar(['stock_no' => 'E04030', 'title' => 'Toyota & Co']);
    $car->addMedia(UploadedFile::fake()->image('front.jpg', 1600, 1200))->toMediaCollection('photos');

    $body = $this->get('/feeds/jct/stock.xml?token=jct-secret-123')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->getContent();

    $xml = simplexml_load_string($body);
    expect($body)->toMatch('/^<\?xml version="1.0" encoding="utf-8" standalone="yes"\?>/i')
        ->and($xml->getName())->toBe('vehicle_list')
        ->and((string) $xml->total_vehicle)->toBe('1')
        ->and((string) $xml->vehicle[0]->s_no)->toBe('1')
        ->and((string) $xml->vehicle[0]->veh_title)->toBe('Toyota & Co')
        ->and((string) $xml->vehicle[0]->stock_number)->toBe('E04030')
        ->and((string) $xml->vehicle[0]->picture->url[0])->toMatch('/^\d+-[a-f0-9]{16}\.jpg$/');
});

it('serves photos as JPEG within 1024 x 800 and 512 KB, by signed name only', function () {
    $car = jctCar();
    $car->addMedia(UploadedFile::fake()->image('front.jpg', 2000, 1500))->toMediaCollection('photos');
    $media = $car->fresh()->getFirstMedia('photos');
    $name = JctImages::fileName($media);

    $response = $this->get('/feeds/jct/images/'.$name)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    [$width, $height] = getimagesize($response->getFile()->getPathname());
    expect($width)->toBeLessThanOrEqual(1024)->and($height)->toBeLessThanOrEqual(800)
        ->and(filesize($response->getFile()->getPathname()))->toBeLessThanOrEqual(512 * 1024);

    // Guessing another id does not work.
    $this->get('/feeds/jct/images/'.$media->id.'-0000000000000000.jpg')->assertNotFound();

    // Sold cars' photos leave with the car.
    $car->update(['status' => 'sold']);
    $this->get('/feeds/jct/images/'.$name)->assertNotFound();
});

it('caps the feed at the membership limit and at 10 photos per car', function () {
    config(['jct_feed.max_vehicles' => 2]);
    jctCar(['published_at' => now()->subDays(3)]);
    $newest = jctCar(['published_at' => now()]);
    jctCar(['published_at' => now()->subDay()]);
    foreach (range(1, 12) as $i) {
        $newest->addMedia(UploadedFile::fake()->image("p{$i}.jpg", 40, 30))->toMediaCollection('photos');
    }

    $data = JctStockFeed::toArray()['data'];

    expect((array) $data)->toHaveCount(2)
        ->and($data->{'1'}['stock_number'])->toBe($newest->stock_no)
        ->and($data->{'1'}['picture'])->toHaveCount(10);
});
