<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Database\Seeders\PartnerPermissionSeeder;
use App\Modules\PartnerApi\Domain\Usage\Quota;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use App\Modules\PartnerApi\Models\PartnerWebhookDelivery;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use Database\Seeders\RoleSeeder;
use Tests\PartnerApi\Support\Partners;

/*
 * Admin alerts (UI brief 6.1), anomaly flags (SRS §8.3), dashboard (6.2).
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PartnerPermissionSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('admin');
    $this->sales = User::factory()->create();
    $this->sales->assignRole('sales');
    $this->car = Vehicle::factory()->create(['status' => 'published', 'stock_no' => 'E07001']);
});

function bell(User $user): array
{
    return $user->fresh()->notifications()->pluck('data')->map(fn ($data) => is_array($data) ? $data['title'] : json_decode($data, true)['title'])->all();
}

it('rings the owners when a buyer or partner holds a car, not for their own staff holds', function () {
    app(HoldService::class)->claim($this->car, new HoldClaim(channel: VehicleHold::CHANNEL_WEB_TT, buyerRef: 'TOCO-1', buyer: $this->sales));
    expect(bell($this->owner))->toBe(['Car on hold: E07001'])->and(bell($this->sales))->toBe([]);

    $other = Vehicle::factory()->create(['status' => 'published']);
    app(HoldService::class)->claim($other, new HoldClaim(channel: VehicleHold::CHANNEL_ADMIN, buyerRef: 'Walk-in', actor: $this->owner));
    expect(bell($this->owner))->toHaveCount(1);
});

it('warns once about holds ending within 6 hours', function () {
    app(HoldService::class)->claim($this->car, new HoldClaim(channel: VehicleHold::CHANNEL_ADMIN, buyerRef: 'JWT buyer', hours: 24, actor: $this->owner));
    $this->travel(19)->hours();

    $this->artisan('partners:monitor')->assertSuccessful();
    $this->artisan('partners:monitor')->assertSuccessful();

    expect(bell($this->owner))->toBe(['Hold ending within 6 hours: E07001']);
});

it('flags notifications failing for over an hour', function () {
    $partner = Partners::make(['name' => 'JWT Japan', 'webhook_url' => 'https://hooks.example/toco']);
    PartnerWebhookDelivery::create(['partner_id' => $partner->id, 'delivery_id' => 'whd_x', 'event' => 'vehicle.sold',
        'payload' => [], 'status' => 'pending', 'attempts' => 3, 'last_error' => 'Partner answered HTTP 500']);
    $this->travel(2)->hours();

    $this->artisan('partners:monitor')->assertSuccessful();
    expect(bell($this->owner))->toBe(['Updates to JWT Japan have been failing for over an hour']);
});

it('flags a partner walking through stock numbers (§8.3)', function () {
    $partner = Partners::make(['name' => 'Scraper Ltd']);
    PartnerRequestLog::insert(collect(range(1, 100))->map(fn ($i) => [
        'partner_id' => $partner->id, 'request_id' => 'req_'.$i, 'kind' => 'api', 'method' => 'GET',
        'endpoint' => 'partner.vehicles.show', 'status' => 404, 'duration_ms' => 5, 'created_at' => now(),
    ])->all());

    $this->artisan('partners:monitor')->assertSuccessful();
    expect(bell($this->owner))->toBe(['Unusual activity from Scraper Ltd']);
});

it('tells the owners once a day when a partner passes 80% of its allowance', function () {
    $partner = Partners::make(['name' => 'JWT Japan', 'rate_per_day' => 10]);
    foreach (range(1, 10) as $i) {
        Quota::countDaily($partner, 'api');
    }

    expect(bell($this->owner))->toBe(["JWT Japan has used 80% of today's requests"]);
});

it('reports yesterday\'s unused holds and contracts ending in 14 days, daily', function () {
    app(HoldService::class)->claim($this->car, new HoldClaim(channel: VehicleHold::CHANNEL_ADMIN, buyerRef: 'B', hours: 24, actor: $this->owner));
    $this->travel(25)->hours();
    app(HoldService::class)->expireDue();
    Partners::make(['name' => 'Seller Co', 'contract_ends_on' => now(config('partner_api.timezone'))->addDays(15)->toDateString()]);
    $this->travel(1)->days();

    $this->artisan('partners:monitor --daily')->assertSuccessful();

    expect(bell($this->owner))->toContain("Seller Co's contract ends in 14 days");
});

it('shows partner widgets on the dashboard to owners only', function () {
    app(HoldService::class)->claim($this->car, new HoldClaim(channel: VehicleHold::CHANNEL_ADMIN, buyerRef: 'B', actor: $this->owner));
    Partners::make(['name' => 'Ending Co', 'contract_ends_on' => now()->addDays(10)->toDateString()]);

    \Filament\Facades\Filament::setCurrentPanel('admin');
    $this->actingAs($this->owner);
    expect(\App\Modules\PartnerApi\Filament\Widgets\PartnerNetworkStats::canView())->toBeTrue();
    \Livewire\Livewire::test(\App\Modules\PartnerApi\Filament\Widgets\PartnerNetworkStats::class)->assertSee('Cars on hold')->assertSee('1');
    \Livewire\Livewire::test(\App\Modules\PartnerApi\Filament\Widgets\NeedsAttention::class)->assertSee('Ending Co')->assertSee('Contract ends');
    $this->get('/admin')->assertOk();

    $this->actingAs($this->sales);
    expect(\App\Modules\PartnerApi\Filament\Widgets\PartnerNetworkStats::canView())->toBeFalse()
        ->and(\App\Modules\PartnerApi\Filament\Widgets\NeedsAttention::canView())->toBeFalse();
});
