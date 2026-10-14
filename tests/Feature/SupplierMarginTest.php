<?php

use App\Models\Supplier;
use App\Models\SupplierImport;
use App\Models\User;
use App\Models\Vehicle;
use App\Settings\StockSettings;
use App\Suppliers\Feeds\OnePriceCsvFeed;
use App\Suppliers\SupplierImporter;
use Database\Seeders\BodyTypeSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(BodyTypeSeeder::class);
    DB::table('currencies')->insert([
        ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'rate_to_usd' => 100, 'is_active' => true, 'sort_order' => 1],
    ]);
    $this->oneprice = Supplier::query()->where('slug', 'oneprice')->firstOrFail();
});

function marginImport(int $id, int $retail, ?array $override = null): SupplierImport
{
    $row = array_fill_keys(OnePriceCsvFeed::COLUMNS, '');
    $row = array_merge($row, ['vehicle_id' => $id, 'retail_price' => $retail, 'initial_registration_date' => '201805',
        'manufacturer' => 'TOYOTA', 'model_name' => 'PRIUS', 'vehicle_size_length' => 4540, 'vehicle_size_width' => 1760, 'vehicle_size_height' => 1470]);
    $path = tempnam(sys_get_temp_dir(), 'op').'.csv';
    $fh = fopen($path, 'w');
    fputcsv($fh, array_map(fn ($k) => $row[$k], OnePriceCsvFeed::COLUMNS), ',', '"', '');
    fclose($fh);

    $import = SupplierImport::query()->create([
        'supplier_id' => Supplier::query()->where('slug', 'oneprice')->value('id'), 'status' => 'queued', 'mode' => 'partial',
        'original_name' => 't.csv', 'file_path' => $path, 'stats' => ['auto_apply' => true, 'margin_override' => $override],
    ]);

    return app(SupplierImporter::class)->run($import);
}

function setSiteMargin(float $percent, float $fixed): void
{
    setSiteTiers([[0, null, $percent, $fixed]]);
    $s = app(StockSettings::class);
    $s->supplier_margin_percent = $percent;
    $s->supplier_margin_fixed_usd = $fixed;
    $s->save();
}

/** @param  list<array{0: float|int, 1: float|int|null, 2: float|int, 3: float|int}>  $tiers  [from, to, %, fixed] */
function setSiteTiers(array $tiers): void
{
    $s = app(StockSettings::class);
    $s->supplier_margin_tiers = array_map(fn ($t) => ['from_usd' => $t[0], 'to_usd' => $t[1], 'percent' => $t[2], 'fixed_usd' => $t[3]], $tiers);
    $s->save();
}

it('uses the site default margin when the supplier has none', function () {
    setSiteMargin(10, 200);
    marginImport(1, 1_000_000);   // ¥1m / 100 = $10,000 → ×1.10 + 200 = $11,200

    $v = Vehicle::query()->where('supplier_ref', '1')->firstOrFail();
    expect((float) $v->price_fob)->toBe(11200.0)
        ->and($v->supplier_meta['margin']['tiers'])->toEqual([['from_usd' => 0, 'to_usd' => null, 'percent' => 10, 'fixed_usd' => 200]]);
});

it('lets the supplier override each part of the site margin', function () {
    setSiteMargin(10, 200);
    $this->oneprice->update(['settings' => ['margin_percent' => 5]]);   // fixed stays the site's $200

    expect($this->oneprice->fresh()->effectiveMargin())->toBe(['percent' => 5.0, 'fixed_usd' => 200.0]);
    marginImport(1, 1_000_000);
    expect((float) Vehicle::query()->where('supplier_ref', '1')->value('price_fob'))->toBe(10700.0);
});

it('lets one import use its own margin, and the nightly reprice keeps it', function () {
    setSiteMargin(10, 0);
    $import = marginImport(1, 1_000_000, ['percent' => 20, 'fixed_usd' => 0]);
    expect($import->stat('margin', null))->toEqual(['percent' => 20, 'fixed_usd' => 0])
        ->and((float) Vehicle::query()->where('supplier_ref', '1')->value('price_fob'))->toBe(12000.0);

    // Rate moves; the vehicle keeps its 20 % margin.
    DB::table('currencies')->where('code', 'JPY')->update(['rate_to_usd' => 125]);
    $this->artisan('suppliers:reprice', ['supplier' => 'oneprice'])->assertSuccessful();
    expect((float) Vehicle::query()->where('supplier_ref', '1')->value('price_fob'))->toBe(9600.0);   // 8,000 × 1.2

    // "Apply current margin to all" switches it to the supplier/site margin.
    $this->artisan('suppliers:reprice', ['supplier' => 'oneprice', '--remargin' => true])->assertSuccessful();
    $v = Vehicle::query()->where('supplier_ref', '1')->firstOrFail();
    expect((float) $v->price_fob)->toBe(8800.0)->and($v->supplier_meta['margin']['percent'])->toEqual(10);
});

it('picks the margin tier by the converted supplier price (client change 2026-10-14)', function () {
    // Tier 1: $0–2,000 → 20 %; tier 2: $2,001–3,000 → +$200; tier 3: $3,001 and above → 10 % + $100.
    setSiteTiers([[2001, 3000, 0, 200], [0, 2000, 20, 0], [3001, null, 10, 100]]);   // saved out of order on purpose
    $op = $this->oneprice->fresh();

    expect($op->fobFromSource(150_000.0, 100))->toBe(1800.0)      // $1,500 × 1.2
        ->and($op->fobFromSource(200_000.0, 100))->toBe(2400.0)   // $2,000 × 1.2 (edge stays in tier 1)
        ->and($op->fobFromSource(250_000.0, 100))->toBe(2700.0)   // $2,500 + 200
        ->and($op->fobFromSource(200_050.0, 100))->toBe(2410.0)   // $2,000.50 sits in the gap → tier 1, rounded up to $10
        ->and($op->fobFromSource(1_000_000.0, 100))->toBe(11100.0) // $10,000 × 1.1 + 100
        ->and(Supplier::describeMargin($op->effectiveMargin()))->toBe('$0–2,000: 20%; $2,001–3,000: $200; $3,001+: 10% + $100');
});

it('keeps each vehicle on its import tiers in the nightly reprice', function () {
    setSiteTiers([[0, 2000, 20, 0], [2001, null, 0, 200]]);
    marginImport(1, 150_000);   // $1,500 → tier 1 → $1,800
    expect((float) Vehicle::query()->where('supplier_ref', '1')->value('price_fob'))->toBe(1800.0);

    setSiteTiers([[0, null, 0, 0]]);   // site tiers change later…
    DB::table('currencies')->where('code', 'JPY')->update(['rate_to_usd' => 50]);   // …and the rate: $3,000 now
    $this->artisan('suppliers:reprice', ['supplier' => 'oneprice'])->assertSuccessful();
    expect((float) Vehicle::query()->where('supplier_ref', '1')->value('price_fob'))->toBe(3200.0);   // own tiers: tier 2, +$200
});

it('lets a supplier flat margin replace the site tiers', function () {
    setSiteTiers([[0, null, 20, 0]]);
    $this->oneprice->update(['settings' => ['margin_fixed_usd' => 300]]);
    expect($this->oneprice->fresh()->effectiveMargin())->not->toHaveKey('tiers')
        ->and($this->oneprice->fresh()->fobFromSource(150_000.0, 100))->toBe(1800.0);   // $1,500 + 0 % (legacy default) + $300
});

it('saves price tiers from the site settings page', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin);
    \Filament\Facades\Filament::setCurrentPanel('admin');

    \Livewire\Livewire::test(\App\Filament\Admin\Pages\Settings::class)
        ->set('data.stock.supplier_margin_tiers', [
            'a' => ['from_usd' => 2001, 'to_usd' => null, 'percent' => 0, 'fixed_usd' => 200],
            'b' => ['from_usd' => 0, 'to_usd' => 2000, 'percent' => 20, 'fixed_usd' => 0],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(app(StockSettings::class)->refresh()->tiers())->toBe([
        ['from_usd' => 0.0, 'to_usd' => 2000.0, 'percent' => 20.0, 'fixed_usd' => 0.0],
        ['from_usd' => 2001.0, 'to_usd' => null, 'percent' => 0.0, 'fixed_usd' => 200.0],
    ]);
});

it('shows the margin fields in site settings and the import form', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Supplier stock')->assertSee('Proforma invoice');
    $this->actingAs($admin)->get('/admin/supplier-imports/create')->assertOk()->assertSee('Profit margin for this import');
});
