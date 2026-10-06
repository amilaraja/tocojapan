<?php

use App\Models\Country;
use App\Models\ImportRegulation;
use App\Models\Make;
use App\Models\Port;
use App\Models\ProformaInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Notifications\NewProformaInvoice;
use App\Services\ProformaInvoiceService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    $this->au = Country::query()->create(['iso2' => 'AU', 'name' => 'Australia', 'slug' => 'australia', 'is_active' => true]);
    $this->brisbane = Port::query()->create(['country_id' => $this->au->id, 'name' => 'Brisbane', 'slug' => 'brisbane', 'rate_per_m3' => 395, 'is_active' => true]);
    $this->sydney = Port::query()->create(['country_id' => $this->au->id, 'name' => 'Sydney', 'slug' => 'sydney', 'rate_per_m3' => 400, 'is_active' => true]);

    // Country-wide rule: other payments only. Brisbane-specific rule: LC too.
    ImportRegulation::query()->create(['country_id' => $this->au->id, 'is_active' => true, 'payment_modes' => ['other']]);
    ImportRegulation::query()->create(['country_id' => $this->au->id, 'is_active' => true, 'payment_modes' => ['lc', 'other']])
        ->ports()->attach($this->brisbane->id);

    $make = Make::query()->create(['slug' => 'suzuki', 'name' => 'SUZUKI']);
    $model = VehicleModel::query()->create(['make_id' => $make->id, 'slug' => 'carry', 'name' => 'Carry']);
    $this->vehicle = Vehicle::factory()->create([
        'make_id' => $make->id, 'vehicle_model_id' => $model->id, 'title' => '1997 SUZUKI Carry Truck',
        'stock_no' => 'E01948', 'status' => 'published', 'price_fob' => 2100, 'price_fob_discount' => 1950,
        'price_on_request' => false, 'm3' => 8.23, 'chassis_number' => 'DD51B-395385', 'year_first_reg' => 1997,
    ]);
    $this->user = User::factory()->create(['name' => 'Dane Walsh']);
});

it('resolves payment modes per port: a port rule beats the country rule', function () {
    expect(ImportRegulation::portAllowsLc($this->brisbane))->toBeTrue()
        ->and(ImportRegulation::portAllowsLc($this->sydney))->toBeFalse()
        ->and(ImportRegulation::lcPortIds())->toBe([$this->brisbane->id => true]);
});

it('works out the invoice figures like checkout does', function () {
    $f = app(ProformaInvoiceService::class)->figures($this->vehicle, $this->brisbane);

    // FOB 2,100 − discount 150 + insurance + freight 8.23 × 395 = CIF
    expect($f['price_fob'])->toBe(2100.0)
        ->and($f['discount'])->toBe(150.0)
        ->and($f['freight'])->toBe(3250.85)
        ->and($f['total_cif'])->toBe(round(1950 + $f['insurance'] + 3250.85, 2));
});

it('shows the LC option on the vehicle page only for LC ports', function () {
    $html = $this->actingAs($this->user)->get('/vehicles/'.$this->vehicle->slug)->assertOk()->getContent();
    expect($html)->toContain('LC payment accepted')->toContain('LC proforma invoice');
});

it('only lists LC destinations on the form', function () {
    $this->actingAs($this->user)->get(route('proforma.create', [$this->vehicle->slug, 'port_id' => $this->brisbane->id]))
        ->assertOk()->assertSee('Dane Walsh')
        ->assertViewHas('destinations', fn ($d) => $d->pluck('ports')->flatten(1)->pluck('name')->all() === ['Brisbane'])
        ->assertViewHas('selectedPortId', $this->brisbane->id);
});

it('requires sign-in', function () {
    $this->get(route('proforma.create', $this->vehicle->slug))->assertRedirect('/login');
});

it('refuses a port that does not accept LC', function () {
    $this->actingAs($this->user)->post(route('proforma.store', $this->vehicle->slug), [
        'port_id' => $this->sydney->id, 'consignee_name' => 'Dane Walsh', 'consignee_address' => '9 Merivale Ave',
        'consignee_phone' => '+61 1', 'consignee_email' => 'dane@example.com',
    ])->assertSessionHasErrors('port_id');
    expect(ProformaInvoice::query()->count())->toBe(0);
});

it('creates the proforma, notifies sales and lets only the owner download the PDF', function () {
    $this->actingAs($this->user)->post(route('proforma.store', $this->vehicle->slug), [
        'port_id' => $this->brisbane->id, 'consignee_name' => 'Dane Walsh',
        'consignee_address' => "9 Merivale Ave Jimboomba, QLD\nAustralia 4280", 'consignee_phone' => '+61 0000000000',
        'consignee_email' => 'dane@example.com',
    ])->assertRedirect(route('proforma.index'));

    $invoice = ProformaInvoice::query()->firstOrFail();
    expect($invoice->invoice_no)->toBe('E01948')
        ->and($invoice->snapshot['chassis_number'])->toBe('DD51B-395385')
        ->and($invoice->expires_on->toDateString())->toBe(today()->addDays(2)->toDateString());
    Notification::assertSentOnDemand(NewProformaInvoice::class);

    $pdf = $this->actingAs($this->user)->get(route('proforma.download', $invoice));
    $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs(User::factory()->create())->get(route('proforma.download', $invoice))->assertNotFound();

    // A second proforma for the same car gets its own number.
    $second = app(ProformaInvoiceService::class)->create($this->user, $this->vehicle, $this->brisbane, [
        'consignee_name' => 'X', 'consignee_address' => 'Y', 'consignee_phone' => '1', 'consignee_email' => 'x@example.com',
    ]);
    expect($second->invoice_no)->toBe('E01948-2');
});

it('does not offer proformas for quote-only partner stock', function () {
    $this->vehicle->update(['supplier_id' => Supplier::query()->where('slug', 'oneprice')->value('id'), 'supplier_ref' => '9']);
    $this->actingAs($this->user)->get(route('proforma.create', $this->vehicle->slug))
        ->assertRedirect(route('vehicles.show', $this->vehicle->slug));
});

it('lists proforma invoices in the admin', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(ProformaInvoiceService::class)->create($this->user, $this->vehicle, $this->brisbane, [
        'consignee_name' => 'Dane Walsh', 'consignee_address' => 'A', 'consignee_phone' => '1', 'consignee_email' => 'd@example.com',
    ]);
    $this->actingAs(User::factory()->create()->assignRole('admin'))
        ->get('/admin/proforma-invoices')->assertOk()->assertSee('E01948');
    $this->actingAs(User::factory()->create()->assignRole('admin'))
        ->get('/admin/import-regulations')->assertOk()->assertSee('Payment');
});
