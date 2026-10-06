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
    $s = app(StockSettings::class);
    $s->supplier_margin_percent = $percent;
    $s->supplier_margin_fixed_usd = $fixed;
    $s->save();
}

it('uses the site default margin when the supplier has none', function () {
    setSiteMargin(10, 200);
    marginImport(1, 1_000_000);   // ¥1m / 100 = $10,000 → ×1.10 + 200 = $11,200

    $v = Vehicle::query()->where('supplier_ref', '1')->firstOrFail();
    expect((float) $v->price_fob)->toBe(11200.0)
        ->and($v->supplier_meta['margin'])->toEqual(['percent' => 10, 'fixed_usd' => 200]);
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

it('shows the margin fields in site settings and the import form', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Supplier stock')->assertSee('Proforma invoice');
    $this->actingAs($admin)->get('/admin/supplier-imports/create')->assertOk()->assertSee('Profit margin for this import');
});
