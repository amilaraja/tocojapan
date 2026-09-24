<?php

use App\Http\Controllers\CheckoutController;
use App\Models\Country;
use App\Models\Order;
use App\Models\Port;
use App\Models\User;
use App\Models\Vehicle;
use App\Settings\PaymentSettings;
use Srmklive\PayPal\Testing\MockPayPalClient;

/** Controller whose PayPal client is the package's HTTP mock. */
class MockedPayPalCheckoutController extends CheckoutController
{
    public static ?MockPayPalClient $mock = null;

    protected function paypalClient(): \Srmklive\PayPal\Services\PayPal
    {
        return self::$mock->mockProvider();
    }
}

function checkoutPort(): Port
{
    $country = Country::create(['iso2' => 'KE', 'name' => 'Kenya', 'slug' => 'kenya', 'is_active' => true]);

    return Port::create([
        'country_id' => $country->id, 'name' => 'Mombasa', 'slug' => 'mombasa',
        'rate_per_m3' => 40.0, 'is_active' => true,
    ]);
}

beforeEach(function () {
    config()->set('paypal.mode', 'sandbox');
    config()->set('paypal.sandbox.client_id', 'x');
    config()->set('paypal.sandbox.client_secret', 'y');

    $settings = app(PaymentSettings::class);
    $settings->paypal_enabled = true;
    $settings->save();

    $this->port = checkoutPort();
    $this->vehicle = Vehicle::factory()->create(['status' => 'published', 'price_fob' => 1650, 'm3' => 10.0]);
    $this->user = User::factory()->create(['email_verified_at' => now()]);

    MockedPayPalCheckoutController::$mock = new MockPayPalClient();
    $this->app->bind(CheckoutController::class, MockedPayPalCheckoutController::class);
});

function validDetails(Port $port): array
{
    return [
        'country_id' => $port->country_id,
        'port_id' => $port->id,
        'ship_to_name' => 'Jane Buyer',
        'ship_to_phone' => '+254700000000',
        'ship_to_address_line1' => '12 Harbour Road',
        'ship_to_city' => 'Mombasa',
        'confirm' => '1',
    ];
}

it('shows the confirmation step instead of jumping straight to PayPal', function () {
    $this->actingAs($this->user)
        ->get(route('checkout.paypal.show', $this->vehicle->slug))
        ->assertOk()
        ->assertSee('PayPal checkout')
        ->assertSee('Continue to PayPal')
        ->assertSee('ship_to_address_line1', false);
});

it('sends the old POST entry point to the confirmation step, not to PayPal', function () {
    $this->actingAs($this->user)
        ->post(route('checkout.start', $this->vehicle->slug))
        ->assertRedirect(route('checkout.paypal.show', $this->vehicle->slug));

    expect(Order::count())->toBe(0);
});

it('refuses to place an order when the confirmation box is unticked', function () {
    $data = validDetails($this->port);
    unset($data['confirm']);

    $this->actingAs($this->user)
        ->post(route('checkout.paypal.place', $this->vehicle->slug), $data)
        ->assertSessionHasErrors('confirm');

    expect(Order::count())->toBe(0);
});

it('charges the CIF total, not the bare FOB price', function () {
    MockedPayPalCheckoutController::$mock->addResponse([
        'id' => 'PPORDER-XYZ',
        'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PPORDER-XYZ']],
    ]);

    $this->actingAs($this->user)
        ->post(route('checkout.paypal.place', $this->vehicle->slug), validDetails($this->port))
        ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PPORDER-XYZ');

    $order = Order::firstOrFail();
    expect($order->payment_provider)->toBe('paypal')
        ->and($order->paypal_order_id)->toBe('PPORDER-XYZ')
        ->and($order->status)->toBe('pending')
        ->and((float) $order->amount_usd)->toBeGreaterThan(1650.0)
        ->and((float) $order->amount_usd)->toBe((float) $order->cif_total)
        ->and($order->ship_to_name)->toBe('Jane Buyer')
        ->and($order->dest_port_id)->toBe($this->port->id);
});

it('keeps the order and explains itself when PayPal will not open an order', function () {
    MockedPayPalCheckoutController::$mock->addResponse(['name' => 'INTERNAL_SERVER_ERROR'], 500);

    $this->actingAs($this->user)
        ->post(route('checkout.paypal.place', $this->vehicle->slug), validDetails($this->port))
        ->assertSessionHasErrors('paypal');

    $order = Order::firstOrFail();
    expect($order->status)->toBe('pending')
        ->and($order->paypal_order_id)->toBeNull();
});

it('turns buyers away while PayPal checkout is disabled', function () {
    $settings = app(PaymentSettings::class);
    $settings->paypal_enabled = false;
    $settings->save();

    $this->actingAs($this->user)
        ->get(route('checkout.paypal.show', $this->vehicle->slug))
        ->assertRedirect(route('vehicles.show', $this->vehicle->slug));
});

it('still lets bank transfer place an order through the shared flow', function () {
    $settings = app(PaymentSettings::class);
    $settings->bank_transfer_enabled = true;
    $settings->save();

    $this->actingAs($this->user)
        ->post(route('checkout.bank.store', $this->vehicle->slug), validDetails($this->port))
        ->assertRedirect();

    $order = Order::firstOrFail();
    expect($order->payment_provider)->toBe('bank_transfer')
        ->and((float) $order->amount_usd)->toBe((float) $order->cif_total);
});
