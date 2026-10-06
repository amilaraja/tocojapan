<?php

use App\Filament\Admin\Resources\SupplierImports\Pages\ViewSupplierImport;
use App\Jobs\RunSupplierImport;
use App\Models\Make;
use App\Models\Supplier;
use App\Models\SupplierImport;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Services\CheckoutFlow;
use App\Suppliers\Feeds\OnePriceCsvFeed;
use App\Suppliers\SupplierImporter;
use Database\Seeders\BodyTypeSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(BodyTypeSeeder::class);
    DB::table('currencies')->insert([
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate_to_usd' => 1, 'is_active' => true, 'sort_order' => 0],
        ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'rate_to_usd' => 150, 'is_active' => true, 'sort_order' => 1],
    ]);
    $this->oneprice = Supplier::query()->where('slug', 'oneprice')->firstOrFail();
});

/** One OnePrice CSV line (29 positional columns) with overridable fields. */
function opLine(int $id, array $o = []): array
{
    $row = array_merge([
        'vehicle_id' => $id, 'wholesale_price' => 1_000_000, 'retail_price' => 1_500_000,
        'initial_registration_date' => '201805', 'manufacturer' => 'TOYOTA', 'model_name' => 'PRIUS',
        'grade' => 'Ｓ　ツーリング', 'vehicle_size_length' => 4540, 'vehicle_size_width' => 1760,
        'vehicle_size_height' => 1470, 'model_code' => 'ZVW50', 'chassis_number' => 'ZVW50-1234567',
        'mileage' => 52000, 'body_type' => 3, 'steering_position' => 12, 'transmission' => 53,
        'number_of_doors' => 4, 'engine_displacement' => 1800, 'seating_capacity' => 5, 'fuel_type' => 21,
        'exterior_color' => 47, 'drive_system' => 9, 'options' => '001;002;013;041',
        'images' => "http://www.919919.jp/avn/photo/car/{$id}_carP_l_1.jpg;http://www.919919.jp/avn/photo/car/{$id}_carP_l_3.jpg",
        'listing_sheet' => "http://www.919919.jp/avn/photo/car/{$id}_carEP_3.jpg", 'listing_store_prefecture' => '京都府',
        'listing_store_city' => '京都市', 'evaluation_score' => '4.5', 'update_date_time' => 1766045942,
    ], $o);

    return array_map(fn ($k) => $row[$k], OnePriceCsvFeed::COLUMNS);
}

/** Write a CSV and run a full import end-to-end (auto-approved unless told otherwise). */
function runImport(array $lines, string $mode = 'full', bool $apply = true, bool $force = false): SupplierImport
{
    $path = tempnam(sys_get_temp_dir(), 'op').'.csv';
    $fh = fopen($path, 'w');
    foreach ($lines as $line) {
        fputcsv($fh, $line, ',', '"', '');
    }
    fclose($fh);

    $import = SupplierImport::query()->create([
        'supplier_id' => Supplier::query()->where('slug', 'oneprice')->value('id'),
        'status' => SupplierImport::STATUS_QUEUED,
        'mode' => $mode,
        'original_name' => 'test.csv',
        'file_path' => $path,
        'stats' => ['auto_apply' => $apply, 'force' => $force],
    ]);

    return app(SupplierImporter::class)->run($import);
}

it('parses a OnePrice line into the normalised payload', function () {
    $parsed = (new OnePriceCsvFeed)->parseLine(array_map('strval', opLine(42, ['model_name' => 'TOYOTA PRIUS'])), 1);
    $p = $parsed['payload'];

    expect($parsed['error'])->toBeNull()
        ->and($p['model'])->toBe('PRIUS')            // maker prefix stripped
        ->and($p['grade'])->toBe('S ツーリング')         // full-width ASCII normalised
        ->and($p['year'])->toBe(2018)->and($p['month'])->toBe(5)
        ->and($p['transmission'])->toBe('cvt')
        ->and($p['fuel'])->toBe('hybrid')
        ->and($p['body_type'])->toBe('hatchback')
        ->and($p['doors'])->toBe(5)                  // door_master code 4 = 5 doors
        ->and($p['length_cm'])->toBe(454.0)
        ->and($p['location'])->toBe('Kyoto, Japan')
        ->and($p['photos'][0])->toStartWith('https://')
        ->and($p['features']['safety'])->toHaveKeys(['abs', 'driver_airbag'])
        ->and($p['meta']['extras'])->toHaveKey('no_accident');
});

it('files vehicles without a model under OTHER and rejects lines without a maker', function () {
    $feed = new OnePriceCsvFeed;
    expect($feed->parseLine(array_map('strval', opLine(1, ['manufacturer' => 'HINO', 'model_name' => ''])), 1)['payload']['model'])->toBe('OTHER')
        ->and($feed->parseLine(array_map('strval', opLine(2, ['manufacturer' => ''])), 2)['error'])->toBe('Missing maker');
});

it('normalises half-width katakana and names aliased makes properly', function () {
    expect((new OnePriceCsvFeed)->parseLine(array_map('strval', opLine(1, ['grade' => 'ﾊｲｳｪｲｽﾀｰ'])), 1)['payload']['grade'])->toBe('ハイウェイスター');

    runImport([opLine(1, ['manufacturer' => 'MCC SMART', 'model_name' => 'FORTWO']), opLine(2, ['manufacturer' => 'U.S.A. OTHER', 'model_name' => 'X'])]);
    expect(Make::query()->where('slug', 'smart')->value('name'))->toBe('SMART')
        ->and(Make::query()->where('slug', 'other')->value('name'))->toBe('OTHER');
});

it('converts Shift-JIS files', function () {
    $line = array_map('strval', opLine(7));
    $sjis = array_map(fn ($v) => mb_convert_encoding($v, 'SJIS-win', 'UTF-8'), $line);
    $p = (new OnePriceCsvFeed)->parseLine($sjis, 1)['payload'];

    expect($p['location'])->toBe('Kyoto, Japan')->and($p['grade'])->toBe('S ツーリング');
});

it('imports new stock as published supplier vehicles priced in USD', function () {
    $import = runImport([opLine(100), opLine(101, ['retail_price' => '', 'manufacturer' => 'AMERICA TOYOTA'])]);

    expect($import->status)->toBe('completed');
    $v = Vehicle::query()->where('supplier_ref', '100')->firstOrFail();

    expect($v->supplier_id)->toBe($this->oneprice->id)
        ->and($v->status)->toBe('published')
        ->and($v->stock_no)->toBe('OP-100')
        ->and($v->slug)->toBe('2018-toyota-prius-op-100')
        ->and((float) $v->price_fob)->toBe(10000.0)          // ¥1.5m / 150, rounded up to $10
        ->and($v->price_on_request)->toBeFalse()
        ->and($v->m3)->not->toBeNull()
        ->and($v->externalPhotoUrls())->toHaveCount(2)
        ->and($v->isSupplierStock())->toBeTrue();

    // No retail price → on request; "AMERICA TOYOTA" folds into the Toyota make.
    $v2 = Vehicle::query()->where('supplier_ref', '101')->firstOrFail();
    expect($v2->price_on_request)->toBeTrue()->and($v2->make_id)->toBe($v->make_id);
});

it('updates the same vehicle on re-import without changing its URL', function () {
    runImport([opLine(100), opLine(200)]);
    $before = Vehicle::query()->where('supplier_ref', '100')->firstOrFail();

    $second = runImport([opLine(100, ['retail_price' => 1_800_000, 'mileage' => 60000]), opLine(200)]);

    $after = $before->fresh();
    expect($after->id)->toBe($before->id)
        ->and($after->slug)->toBe($before->slug)
        ->and((float) $after->price_fob)->toBe(12000.0)
        ->and($after->mileage_km)->toBe(60000)
        ->and($second->stat('counts')['update'])->toBe(1)
        ->and($second->stat('counts')['unchanged'])->toBe(1)
        ->and($second->stat('counts')['price_up'])->toBe(1);
});

it('delists vehicles missing from a full file and relists them when they return', function () {
    runImport([opLine(100), opLine(200), opLine(300), opLine(400)]);
    $gone = Vehicle::query()->where('supplier_ref', '400')->firstOrFail();

    $second = runImport([opLine(100), opLine(200), opLine(300)]);
    expect($gone->fresh()->status)->toBe('delisted')
        ->and($gone->fresh()->delisted_at)->not->toBeNull()
        ->and($second->stat('applied')['delisted'])->toBe(1);

    // The old URL keeps working: 301 to similar stock instead of 404.
    $this->get('/vehicles/'.$gone->slug)
        ->assertStatus(301)
        ->assertRedirectContains('make=toyota');

    runImport([opLine(100), opLine(200), opLine(300), opLine(400)]);
    expect($gone->fresh()->status)->toBe('published')->and($gone->fresh()->slug)->toBe($gone->slug);
    $this->get('/vehicles/'.$gone->slug)->assertOk();
});

it('never delists on a partial file', function () {
    runImport([opLine(100), opLine(200)]);
    runImport([opLine(300)], 'partial');

    expect(Vehicle::query()->where('supplier_ref', '200')->value('status'))->toBe('published')
        ->and(Vehicle::query()->where('supplier_id', $this->oneprice->id)->count())->toBe(3);
});

it('holds a suspicious full file for confirmation (delist guard)', function () {
    runImport([opLine(100), opLine(200), opLine(300), opLine(400)]);

    $cut = runImport([opLine(100)]);  // 75 % would be delisted, guard is 30 %
    expect($cut->status)->toBe('previewed')
        ->and($cut->needsDelistConfirmation())->toBeTrue()
        ->and(Vehicle::query()->where('status', 'delisted')->count())->toBe(0);

    expect(fn () => app(SupplierImporter::class)->approve($cut))->toThrow(RuntimeException::class);

    app(SupplierImporter::class)->approve($cut, confirmDelist: true);
    app(SupplierImporter::class)->run($cut->fresh());
    expect(Vehicle::query()->where('status', 'delisted')->count())->toBe(3);
});

it('waits for approval when not auto-applied, and cancelling changes nothing', function () {
    $import = runImport([opLine(100)], apply: false);
    expect($import->status)->toBe('previewed')
        ->and(Vehicle::query()->where('supplier_id', $this->oneprice->id)->count())->toBe(0);

    app(SupplierImporter::class)->cancel($import);
    expect($import->fresh()->status)->toBe('cancelled')->and($import->rows()->count())->toBe(0);
});

it('keeps admin edits on locked vehicles', function () {
    runImport([opLine(100)]);
    $v = Vehicle::query()->where('supplier_ref', '100')->firstOrFail();
    $v->update(['price_fob' => 9999, 'sync_locked' => true]);

    runImport([opLine(100, ['retail_price' => 3_000_000])]);
    expect((float) $v->fresh()->price_fob)->toBe(9999.0);
});

it('reports bad lines without stopping the import', function () {
    $import = runImport([opLine(100), opLine(101, ['initial_registration_date' => 'abc']), ['', 'x']]);

    expect($import->status)->toBe('completed')
        ->and($import->error_rows)->toBe(2)
        ->and($import->errors_sample[0]['ref'])->toBe('101');
});

it('reprices supplier stock from the stored supplier price', function () {
    runImport([opLine(100)]);
    $this->oneprice->update(['settings' => ['margin_percent' => 10]]);
    DB::table('currencies')->where('code', 'JPY')->update(['rate_to_usd' => 100]);

    $this->artisan('suppliers:reprice', ['supplier' => 'oneprice', '--remargin' => true])->assertSuccessful();

    // ¥1.5m / 100 × 1.10 = $16,500
    expect((float) Vehicle::query()->where('supplier_ref', '100')->value('price_fob'))->toBe(16500.0);
});

it('keeps supplier stock out of online checkout unless the supplier allows it', function () {
    runImport([opLine(100)]);
    $v = Vehicle::query()->where('supplier_ref', '100')->firstOrFail();

    expect(app(CheckoutFlow::class)->eligibilityError($v))->toContain('quote');

    $this->oneprice->update(['settings' => ['allow_online_checkout' => true]]);
    expect(app(CheckoutFlow::class)->eligibilityError($v->fresh()))->toBeNull();
});

it('redirects old WordPress OnePrice URLs', function () {
    runImport([opLine(29225)]);
    $slug = Vehicle::query()->where('supplier_ref', '29225')->value('slug');

    $this->get('/vehicle/29225')->assertStatus(301)->assertRedirect(route('vehicles.show', $slug));
    $this->get('/vehicle/1')->assertStatus(301)->assertRedirectContains('supplier=oneprice');
    $this->get('/one-price')->assertStatus(301)->assertRedirectContains('supplier=oneprice');
});

it('lists own stock before supplier stock and filters by supplier', function () {
    runImport([opLine(100)]);
    $make = Make::query()->where('slug', 'toyota')->first();
    $model = VehicleModel::query()->where('make_id', $make->id)->first();
    Vehicle::factory()->create([
        'make_id' => $make->id, 'vehicle_model_id' => $model->id, 'status' => 'published',
        'published_at' => now()->subYear(), 'title' => 'Old Own Stock Car',
    ]);

    $this->get('/vehicles')->assertOk()->assertSeeInOrder(['Old Own Stock Car', '2018 TOYOTA PRIUS']);
    $this->get('/vehicles?supplier=oneprice')->assertOk()->assertDontSee('Old Own Stock Car')->assertSee('2018 TOYOTA PRIUS');
});

it('renders a supplier vehicle page with hotlinked photos and no checkout buttons', function () {
    runImport([opLine(100)]);
    $v = Vehicle::query()->where('supplier_ref', '100')->firstOrFail();

    $this->get('/vehicles/'.$v->slug)
        ->assertOk()
        ->assertSee('https://www.919919.jp/avn/photo/car/100_carP_l_1.jpg', false)
        ->assertSee('Partner stock')
        ->assertDontSee('Buy with bank transfer');
});

it('unpacks gzipped uploads', function () {
    $csv = tempnam(sys_get_temp_dir(), 'op');
    file_put_contents($csv.'.csv.gz', gzencode("a,b\n"));
    $out = SupplierImporter::unpack($csv.'.csv.gz');

    expect($out)->toEndWith('.csv')->and(file_get_contents($out))->toBe("a,b\n");
});

it('renders the supplier admin screens for admins only', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('admin');
    $sales = User::factory()->create()->assignRole('sales');

    $import = runImport([opLine(100), opLine(200)], apply: false);
    runImport([opLine(300)], 'partial');

    $this->actingAs($admin)->get('/admin/suppliers')->assertOk()->assertSee('OnePrice');
    $this->actingAs($admin)->get('/admin/suppliers/'.$this->oneprice->id.'/edit')->assertOk()->assertSee('Profit margin');
    $this->actingAs($admin)->get('/admin/supplier-imports')->assertOk();
    $this->actingAs($admin)->get('/admin/supplier-imports/create')->assertOk()->assertSee('complete current stock');
    $this->actingAs($admin)->get('/admin/supplier-imports/'.$import->id)->assertOk()->assertSee('Ready to review')->assertSee('Approve');
    $this->actingAs($admin)->get('/admin/vehicles')->assertOk();

    $v = Vehicle::query()->where('supplier_ref', '300')->firstOrFail();
    $this->actingAs($admin)->get('/admin/vehicles/'.$v->id.'/edit')->assertOk()->assertSee('Supplier retail price');

    // Sales can run imports but not change supplier pricing.
    $this->actingAs($sales)->get('/admin/supplier-imports')->assertOk();
    $this->actingAs($sales)->get('/admin/suppliers')->assertForbidden();
});

it('approves a previewed import from the review screen', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('admin');
    $import = runImport([opLine(100)], apply: false);

    Filament::setCurrentPanel('admin');
    Queue::fake();
    $this->actingAs($admin);
    Livewire::test(ViewSupplierImport::class, ['record' => $import->id])
        ->callAction('approve');

    expect($import->fresh()->status)->toBe('applying');
    Queue::assertPushed(RunSupplierImport::class);
});
