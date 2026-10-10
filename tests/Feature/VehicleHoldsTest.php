<?php

use App\Filament\Admin\Resources\Vehicles\Pages\EditVehicle;
use App\Models\Country;
use App\Models\Order;
use App\Models\Port;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Models\Partner;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use App\Services\Holds\VehicleUnavailable;
use App\Settings\PaymentSettings;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Vehicle holds and double-sell prevention (SRS-TOC-PAPI section 7,
 * MIS task #204). TOC-PAPI-FR-HLD-01..08.
 */

beforeEach(function () {
    $this->holds = app(HoldService::class);
    $this->vehicle = Vehicle::factory()->create(['status' => 'published', 'price_fob' => 5000, 'm3' => 10.0]);
});

function adminClaim(string $ref = 'Walk-in buyer', ?int $hours = null, ?Partner $referral = null): HoldClaim
{
    return new HoldClaim(channel: VehicleHold::CHANNEL_ADMIN, buyerRef: $ref, hours: $hours, referralPartner: $referral);
}

function webClaim(User $buyer, string $channel = VehicleHold::CHANNEL_WEB_TT): HoldClaim
{
    return new HoldClaim(channel: $channel, buyerRef: 'web', buyer: $buyer);
}

function holdsPort(): Port
{
    $country = Country::create(['iso2' => 'KE', 'name' => 'Kenya', 'slug' => 'kenya', 'is_active' => true]);

    return Port::create(['country_id' => $country->id, 'name' => 'Mombasa', 'slug' => 'mombasa', 'rate_per_m3' => 40.0, 'is_active' => true]);
}

function holdsCheckoutDetails(Port $port): array
{
    return [
        'country_id' => $port->country_id, 'port_id' => $port->id, 'ship_to_name' => 'Jane Buyer',
        'ship_to_phone' => '+254700000000', 'ship_to_address_line1' => '12 Harbour Road', 'ship_to_city' => 'Mombasa', 'confirm' => '1',
    ];
}

describe('hold service', function () {
    it('lets the first claim win and refuses every other channel (FR-HLD-01)', function () {
        $hold = $this->holds->claim($this->vehicle, adminClaim(hours: 48));

        expect($this->vehicle->fresh()->status)->toBe('reserved')
            ->and($hold->price_snapshot)->toEqual('5000.00')
            ->and($hold->expires_at->diffInHours(now(), true))->toBeGreaterThan(47);

        $buyer = User::factory()->create();
        expect(fn () => $this->holds->claim($this->vehicle, webClaim($buyer)))
            ->toThrow(fn (VehicleUnavailable $e) => expect($e->reason)->toBe('held')->and($e->currentHold->id)->toBe($hold->id));
    });

    it('allows only one active hold per car at the database level', function () {
        $this->holds->claim($this->vehicle, adminClaim());

        expect(fn () => VehicleHold::create([
            'vehicle_id' => $this->vehicle->id, 'active_vehicle_id' => $this->vehicle->id,
            'channel' => 'admin', 'buyer_ref' => 'sneaky', 'state' => 'active',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('lets one website buyer move between payment methods on the same hold', function () {
        $buyer = User::factory()->create();
        $first = $this->holds->claim($this->vehicle, webClaim($buyer, VehicleHold::CHANNEL_WEB_PAYPAL));
        $second = $this->holds->claim($this->vehicle, webClaim($buyer, VehicleHold::CHANNEL_PROFORMA));

        expect($second->id)->toBe($first->id)->and(VehicleHold::count())->toBe(1);
    });

    it('refuses cars that are not on sale', function (string $status) {
        $this->vehicle->update(['status' => $status]);

        expect(fn () => $this->holds->claim($this->vehicle, adminClaim()))
            ->toThrow(fn (VehicleUnavailable $e) => expect($e->reason)->toBe('not_for_sale'));
    })->with(['draft', 'sold', 'delisted']);

    it('returns cars to sale when holds expire, released or lapsed on the next claim (FR-HLD-04)', function () {
        $hold = $this->holds->claim($this->vehicle, adminClaim(hours: 24));
        $this->travel(25)->hours();

        expect($this->holds->expireDue())->toBe(1)
            ->and($hold->fresh()->state)->toBe('expired')
            ->and($this->vehicle->fresh()->status)->toBe('published');

        // A lapsed hold the scheduler has not reached yet does not block a new claim.
        $this->holds->claim($this->vehicle, adminClaim(hours: 24));
        $this->travel(25)->hours();
        $next = $this->holds->claim($this->vehicle, webClaim(User::factory()->create()));
        expect($next->isActive())->toBeTrue()->and(VehicleHold::where('state', 'expired')->count())->toBe(2);

        $this->holds->release($next, 'Buyer walked away');
        expect($this->vehicle->fresh()->status)->toBe('published');
    });

    it('extends a hold from its current end time', function () {
        $hold = $this->holds->claim($this->vehicle, adminClaim(hours: 24));
        $end = $hold->expires_at->copy();

        $hold = $this->holds->extend($hold, 12);
        expect($hold->expires_at->equalTo($end->addHours(12)))->toBeTrue()->and($hold->extensions)->toBe(1);
    });

    it('confirms a sale: converted hold, sold car with sold_at (FR-HLD-05)', function () {
        $hold = $this->holds->claim($this->vehicle, adminClaim());
        $this->holds->confirmSale($hold);

        $vehicle = $this->vehicle->fresh();
        expect($hold->fresh()->state)->toBe('converted')
            ->and($hold->fresh()->active_vehicle_id)->toBeNull()
            ->and($vehicle->status)->toBe('sold')
            ->and($vehicle->sold_at)->not->toBeNull();
    });

    it('sells an unheld car through the same lock, crediting a listing partner', function () {
        $jwt = Partner::create(['name' => 'JWT Japan']);
        $hold = $this->holds->sellUnheld($this->vehicle, adminClaim('JWT inquiry 55821', referral: $jwt));

        expect($hold->state)->toBe('converted')->and($hold->referral_partner_id)->toBe($jwt->id)
            ->and($hold->holderLabel())->toBe('Admin, for JWT Japan')
            ->and($this->vehicle->fresh()->status)->toBe('sold');
    });

    it('caps active holds per partner and replays idempotent requests (FR-HLD-07)', function () {
        $partner = Partner::create(['name' => 'Seller Co', 'type' => 'selling', 'max_active_holds' => 1]);
        $claim = fn (Vehicle $v, string $key) => new HoldClaim(channel: 'partner', buyerRef: 'B1', partner: $partner, idempotencyKey: $key);

        $first = $this->holds->claim($this->vehicle, $claim($this->vehicle, 'k1'));
        expect($this->holds->claim($this->vehicle, $claim($this->vehicle, 'k1'))->id)->toBe($first->id);

        $other = Vehicle::factory()->create(['status' => 'published', 'price_fob' => 100]);
        expect(fn () => $this->holds->claim($other, $claim($other, 'k2')))
            ->toThrow(fn (VehicleUnavailable $e) => expect($e->reason)->toBe('hold_limit'));
    });

    it('keeps the hold table in step with status changes made in the vehicle form', function () {
        $hold = $this->holds->claim($this->vehicle, adminClaim());
        $this->vehicle->fresh()->update(['status' => 'published']);
        expect($hold->fresh()->state)->toBe('released');

        $hold = $this->holds->claim($this->vehicle, adminClaim());
        $this->vehicle->fresh()->update(['status' => 'sold']);
        expect($hold->fresh()->state)->toBe('converted')->and($this->vehicle->fresh()->sold_at)->not->toBeNull();
    });

    it('stamps sold_at whenever a car becomes sold, so it keeps its 90-day SOLD badge', function () {
        $this->vehicle->update(['status' => 'sold']);

        expect($this->vehicle->fresh()->sold_at)->not->toBeNull()
            ->and(Vehicle::published()->whereKey($this->vehicle->id)->exists())->toBeTrue();
    });
});

describe('own channels (FR-HLD-03)', function () {
    beforeEach(function () {
        config(['holds.enforced' => true]);
        $settings = app(PaymentSettings::class);
        $settings->bank_transfer_enabled = true;
        $settings->save();
        $this->port = holdsPort();
        $this->buyer = User::factory()->create(['email_verified_at' => now()]);
    });

    it('holds the car when a bank-transfer order is placed and turns the next buyer away', function () {
        $this->actingAs($this->buyer)->post(route('checkout.bank.store', $this->vehicle->slug), holdsCheckoutDetails($this->port))->assertRedirect();

        $order = Order::sole();
        $hold = VehicleHold::sole();
        expect($hold->order_id)->toBe($order->id)->and($hold->channel)->toBe('web_tt')
            ->and($this->vehicle->fresh()->status)->toBe('reserved');

        $rival = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($rival)->post(route('checkout.bank.store', $this->vehicle->slug), holdsCheckoutDetails($this->port))
            ->assertRedirect(route('vehicles.show', $this->vehicle->slug))->assertSessionHas('vehicle_reserved');
        $this->actingAs($rival)->get(route('checkout.bank.show', $this->vehicle->slug))->assertSessionHas('vehicle_reserved');

        expect(Order::count())->toBe(1);
    });

    it('frees the car when the order is cancelled and keeps it with no expiry once paid', function () {
        $this->actingAs($this->buyer)->post(route('checkout.bank.store', $this->vehicle->slug), holdsCheckoutDetails($this->port));
        $order = Order::sole();

        $order->transitionTo('paid');
        expect(VehicleHold::sole()->expires_at)->toBeNull();

        $order->transitionTo('cancelled');
        expect(VehicleHold::sole()->state)->toBe('released')->and($this->vehicle->fresh()->status)->toBe('published');
    });

    it('never takes PayPal money for a car someone else now holds', function () {
        $order = Order::create(['user_id' => $this->buyer->id, 'vehicle_id' => $this->vehicle->id, 'amount_usd' => 5400,
            'currency' => 'USD', 'status' => 'pending', 'payment_provider' => 'paypal']);
        $this->holds->claim($this->vehicle, adminClaim());

        expect($this->holds->ensureForOrder($order))->toBeFalse();

        $this->holds->release($this->holds->activeHold($this->vehicle), 'test');
        expect($this->holds->ensureForOrder($order))->toBeTrue()
            ->and(VehicleHold::active()->where('order_id', $order->id)->exists())->toBeTrue();
    });

    it('notes a paid order whose car is held elsewhere instead of failing silently', function () {
        $order = Order::create(['user_id' => $this->buyer->id, 'vehicle_id' => $this->vehicle->id, 'amount_usd' => 5400,
            'currency' => 'USD', 'status' => 'pending', 'payment_provider' => 'bank_transfer']);
        $this->holds->claim($this->vehicle, adminClaim());

        $order->transitionTo('paid');
        expect($order->fresh()->admin_notes)->toContain('HELD BY ANOTHER CHANNEL');
    });

    it('places no holds while enforcement is off', function () {
        config(['holds.enforced' => false]);
        $this->actingAs($this->buyer)->post(route('checkout.bank.store', $this->vehicle->slug), holdsCheckoutDetails($this->port));

        expect(Order::count())->toBe(1)->and(VehicleHold::count())->toBe(0)->and($this->vehicle->fresh()->status)->toBe('published');
    });
});

describe('public site (UI brief 7)', function () {
    it('shows a reserved car as Under offer with buying switched off', function () {
        $this->holds->claim($this->vehicle, adminClaim());
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($buyer)->get(route('vehicles.show', $this->vehicle->slug))->assertOk()
            ->assertSee('Under offer')
            ->assertSee('Reserved for another buyer. If the reservation lapses, it returns to sale automatically.')
            ->assertDontSee(route('checkout.bank.show', $this->vehicle->slug));

        $this->get(route('vehicles.index'))->assertOk()->assertSee('Under offer');
    });

    it('shows the friendly race message after a lost checkout', function () {
        $this->holds->claim($this->vehicle, adminClaim());

        $this->withSession(['vehicle_reserved' => true])->get(route('vehicles.show', $this->vehicle->slug))
            ->assertSee('Someone else just reserved this vehicle.');
    });
});

describe('admin (UI brief 5)', function () {
    beforeEach(function () {
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Toco Admin']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
    });

    it('holds a car for a JWT Japan buyer, extends, releases and sells it', function () {
        $jwt = Partner::create(['name' => 'JWT Japan']);
        $page = Livewire::test(EditVehicle::class, ['record' => $this->vehicle->getRouteKey()]);

        $page->callAction('holdForBuyer', ['source' => 'partner:'.$jwt->id, 'buyer_ref' => 'JWT inquiry 55821', 'hours' => 48])
            ->assertHasNoActionErrors();
        $hold = VehicleHold::sole();
        expect($hold->referral_partner_id)->toBe($jwt->id)->and($hold->created_by)->toBe($this->admin->id)
            ->and($this->vehicle->fresh()->status)->toBe('reserved');

        $page->callAction('extendHold', ['hours' => 24])->assertHasNoActionErrors();
        expect($hold->fresh()->extensions)->toBe(1);

        $page->callAction('markSold')->assertHasNoActionErrors();
        expect($hold->fresh()->state)->toBe('converted')->and($this->vehicle->fresh()->status)->toBe('sold');
    });

    it('marks an unheld car sold with its channel and buyer reference', function () {
        Livewire::test(EditVehicle::class, ['record' => $this->vehicle->getRouteKey()])
            ->callAction('markSold', ['source' => 'direct', 'buyer_ref' => 'Mr Ito'])
            ->assertHasNoActionErrors();

        expect(VehicleHold::sole()->buyer_ref)->toBe('Direct: Mr Ito')->and($this->vehicle->fresh()->status)->toBe('sold');
    });

    it('shows who holds the car on the edit page', function () {
        $this->holds->claim($this->vehicle, adminClaim('Mrs Phiri'));

        $this->get('/admin/vehicles/'.$this->vehicle->id.'/edit')->assertOk()->assertSee('Under offer. Held by Admin for Mrs Phiri');
    });
});
