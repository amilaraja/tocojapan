<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Database\Seeders\PartnerPermissionSeeder;
use App\Modules\PartnerApi\Filament\Resources\Holds\Pages\ListHolds;
use App\Modules\PartnerApi\Filament\Resources\Partners\PartnerForm;
use App\Modules\PartnerApi\Filament\Resources\Partners\Pages\CreatePartner;
use App\Modules\PartnerApi\Filament\Resources\Partners\Pages\EditPartner;
use App\Modules\PartnerApi\Filament\Resources\Partners\Pages\ListPartners;
use App\Modules\PartnerApi\Filament\Resources\Partners\RelationManagers\ClientsRelationManager;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerClient;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\PartnerApi\Support\Partners;

/*
 * Partner Network admin (UI brief TOC-PAPI-UI-01 sections 1-3, FR-PRT-01..04).
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PartnerPermissionSeeder::class);
    Filament::setCurrentPanel('admin');
});

function partnerAdmin(): User
{
    $user = User::factory()->create(['name' => 'Owner']);
    $user->assignRole('admin');

    return $user;
}

it('shows no trace of the Partner Network to users without partners.admin', function () {
    $sales = User::factory()->create();
    $sales->assignRole('sales');

    $this->actingAs($sales)->get('/admin/partners')->assertForbidden();
    $this->actingAs($sales)->get('/admin/holds')->assertForbidden();
    $this->actingAs($sales)->get('/admin')->assertOk()->assertDontSee('Partner Network');
});

it('lists partners for an owner with the menu group', function () {
    Partners::make(['name' => 'JWT Japan']);

    $this->actingAs(partnerAdmin())->get('/admin/partners')->assertOk()
        ->assertSee('Partner Network')->assertSee('JWT Japan')->assertSee('All own stock')->assertSee('Toco price');
});

it('creates a partner through the wizard, listing by default', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test(CreatePartner::class)
        ->fillForm([
            'name' => 'JWT Japan', 'country' => null, 'contact_email' => 'tech@jwt.example',
            'price_mode' => 'percent', 'price_markup' => 8,
            'ip_allowlist' => ['203.0.113.0/24'],
            'webhook_url' => 'https://jwt.example/toco',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $partner = Partner::sole();
    expect($partner->type)->toBe('listing')->and((float) $partner->price_markup)->toBe(8.0)
        ->and($partner->ip_allowlist)->toBe(['203.0.113.0/24'])
        ->and($partner->rate_per_day)->toBe(20000);
});

it('refuses plain-http change addresses and bad server addresses', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test(CreatePartner::class)
        ->fillForm(['name' => 'X', 'webhook_url' => 'http://insecure.example', 'ip_allowlist' => ['not-an-ip']])
        ->call('create')
        ->assertHasFormErrors(['webhook_url']);

    expect(PartnerForm::isIpOrRange('203.0.113.0/24'))->toBeTrue()
        ->and(PartnerForm::isIpOrRange('2001:db8::/32'))->toBeTrue()
        ->and(PartnerForm::isIpOrRange('203.0.113.0/40'))->toBeFalse()
        ->and(PartnerForm::isIpOrRange('not-an-ip'))->toBeFalse();
});

it('refuses selling to listing while the partner holds cars (FR-PRT-04)', function () {
    $this->actingAs(partnerAdmin());
    $partner = Partners::make(['type' => 'selling']);
    $car = Vehicle::factory()->create(['status' => 'published']);
    app(HoldService::class)->claim($car, new HoldClaim(channel: VehicleHold::CHANNEL_PARTNER, buyerRef: 'B1', partner: $partner));

    Livewire::test(EditPartner::class, ['record' => $partner->getRouteKey()])
        ->fillForm(['type' => 'listing'])
        ->call('save')
        ->assertHasFormErrors(['type']);

    expect($partner->fresh()->type)->toBe('selling');
});

it('suspends with a reason and revokes only after the name is typed', function () {
    $this->actingAs(partnerAdmin());
    $partner = Partners::make(['name' => 'Seller Co']);

    Livewire::test(ListPartners::class)
        ->callAction(TestAction::make('suspend')->table($partner), ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);
    Livewire::test(ListPartners::class)
        ->callAction(TestAction::make('suspend')->table($partner), ['reason' => 'Unpaid invoice']);
    expect($partner->fresh()->status)->toBe('suspended');

    Livewire::test(ListPartners::class)
        ->callAction(TestAction::make('revoke')->table($partner), ['confirm_name' => 'Seller', 'reason' => 'Ended'])
        ->assertHasFormErrors(['confirm_name']);
    expect($partner->fresh()->status)->toBe('suspended');

    Livewire::test(ListPartners::class)
        ->callAction(TestAction::make('revoke')->table($partner), ['confirm_name' => 'Seller Co', 'reason' => 'Ended']);
    expect($partner->fresh()->status)->toBe('revoked');
});

it('shows a new key once and stores only its hash (FR-PRT-02)', function () {
    $this->actingAs(partnerAdmin());
    $partner = Partners::make();

    $component = Livewire::test(ClientsRelationManager::class, ['ownerRecord' => $partner, 'pageClass' => EditPartner::class])
        ->callAction(TestAction::make('createKey')->table(), ['label' => 'Production', 'environment' => 'live'])
        ->assertActionMounted('showSecret');

    $client = PartnerClient::sole();
    $secret = $component->instance()->mountedActions[0]['arguments']['secret'] ?? null;
    expect($secret)->toStartWith('tcp_live_')
        ->and($client->secret_hash)->not->toContain($secret)
        ->and($client->verifySecret($secret))->toBeTrue();

    // Cannot be dismissed without confirming the copy.
    $component->callMountedAction()->assertHasFormErrors(['copied']);
    $component->fillForm(['copied' => true])->callMountedAction()->assertHasNoFormErrors();

    // Afterwards only the prefix is shown anywhere.
    $this->get('/admin/partners/'.$partner->id.'/edit')->assertOk()->assertDontSee($secret);
});

it('lists active holds with a countdown and releases them', function () {
    $this->actingAs(partnerAdmin());
    $car = Vehicle::factory()->create(['status' => 'published', 'stock_no' => 'E09999']);
    $hold = app(HoldService::class)->claim($car, new HoldClaim(channel: VehicleHold::CHANNEL_ADMIN, buyerRef: 'JWT inquiry 1', hours: 48));

    $this->get('/admin/holds')->assertOk()->assertSee('E09999')->assertSee('JWT inquiry 1')->assertSee('1 on hold');

    Livewire::test(ListHolds::class)
        ->assertCanSeeTableRecords([$hold])
        ->callAction(TestAction::make('release')->table($hold), ['reason' => 'Buyer withdrew']);

    expect($hold->fresh()->state)->toBe('released')->and($car->fresh()->status)->toBe('published');
});

it('serves the partner docs to admins before go-live and to everyone after (FR-DOC-01)', function () {
    config(['partner_api.enabled' => false]);
    $this->get('/partner/docs')->assertNotFound();
    $this->actingAs(partnerAdmin())->get('/partner/docs')->assertOk()
        ->assertSee('Toco Partner Stock API')->assertSee('id="error-partner-suspended"', false);

    auth()->logout();
    config(['partner_api.enabled' => true]);
    $this->get('/partner/docs')->assertOk()->assertSee('Syncing stock');
    $this->get('/partner/docs/openapi.json')->assertOk()->assertJsonPath('openapi', '3.1.0');
});
