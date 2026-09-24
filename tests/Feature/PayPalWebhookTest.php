<?php

use App\Http\Controllers\PayPalWebhookController;
use App\Models\Order;
use App\Models\PayPalWebhookEvent;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Notification;

/**
 * Controller with the signature check stubbed out — every test below is
 * about what we do with a *trusted* payload. The signature path itself is
 * covered separately by the rejection test, which uses the real controller.
 */
class FakeVerifyingPayPalWebhookController extends PayPalWebhookController
{
    protected function signatureIsValid(\Illuminate\Http\Request $request, string $webhookId, string $raw): bool
    {
        return true;
    }
}

beforeEach(function () {
    Notification::fake();

    config()->set('paypal.mode', 'sandbox');
    config()->set('paypal.sandbox.webhook_id', 'WH-TEST-123');

    $this->app->bind(PayPalWebhookController::class, FakeVerifyingPayPalWebhookController::class);

    $this->order = Order::create([
        'user_id' => User::factory()->create()->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'amount_usd' => 4500.00,
        'currency' => 'USD',
        'status' => 'pending',
        'payment_provider' => 'paypal',
        'paypal_order_id' => 'PPORDER-1',
    ]);
});

function captureEvent(Order $order, string $eventId = 'WH-EVT-1', array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $eventId,
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'CAPTURE-1',
            'status' => 'COMPLETED',
            'custom_id' => $order->order_no,
            'amount' => ['currency_code' => 'USD', 'value' => '4500.00'],
            'supplementary_data' => ['related_ids' => ['order_id' => $order->paypal_order_id]],
        ],
    ], $overrides);
}

it('marks an order paid when a capture completes', function () {
    $this->postJson('/webhooks/paypal', captureEvent($this->order))->assertOk();

    $this->order->refresh();
    expect($this->order->status)->toBe('paid')
        ->and($this->order->paypal_capture_id)->toBe('CAPTURE-1')
        ->and($this->order->paid_at)->not->toBeNull();
});

it('notifies the buyer even though they never came back to the site', function () {
    $this->postJson('/webhooks/paypal', captureEvent($this->order))->assertOk();

    Notification::assertSentTo($this->order->user, \App\Notifications\OrderStatusChanged::class);
});

it('ignores a replayed delivery of the same event', function () {
    $payload = captureEvent($this->order);

    $this->postJson('/webhooks/paypal', $payload)->assertOk();
    $this->postJson('/webhooks/paypal', $payload)->assertOk();

    expect(PayPalWebhookEvent::where('event_id', 'WH-EVT-1')->count())->toBe(1);
});

it('refuses to mark an order paid when the captured amount does not match', function () {
    $payload = captureEvent($this->order, 'WH-EVT-2', [
        'resource' => ['amount' => ['value' => '45.00']],
    ]);

    $this->postJson('/webhooks/paypal', $payload)->assertOk();

    $this->order->refresh();
    expect($this->order->status)->toBe('pending')
        ->and($this->order->admin_notes)->toContain('needs review');
});

it('marks an order refunded on a capture refund', function () {
    $this->order->update(['status' => 'paid', 'paypal_capture_id' => 'CAPTURE-1', 'paid_at' => now()]);

    $this->postJson('/webhooks/paypal', [
        'id' => 'WH-EVT-3',
        'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
        'resource' => [
            'id' => 'REFUND-1',
            'custom_id' => $this->order->order_no,
            'amount' => ['currency_code' => 'USD', 'value' => '4500.00'],
        ],
    ])->assertOk();

    expect($this->order->refresh()->status)->toBe('refunded');
});

it('records an unhandled event type without touching the order', function () {
    $this->postJson('/webhooks/paypal', [
        'id' => 'WH-EVT-4',
        'event_type' => 'PAYMENT.CAPTURE.PENDING',
        'resource' => ['id' => 'CAPTURE-1', 'custom_id' => $this->order->order_no],
    ])->assertOk();

    expect($this->order->refresh()->status)->toBe('pending')
        ->and(PayPalWebhookEvent::where('event_id', 'WH-EVT-4')->first()->note)->toContain('No handler');
});

it('rejects a payload whose signature does not verify', function () {
    // Real controller — no stubbed verification, and the body carries no
    // PayPal signature headers, so it must fail closed.
    $this->app->bind(PayPalWebhookController::class, PayPalWebhookController::class);

    $this->postJson('/webhooks/paypal', captureEvent($this->order, 'WH-EVT-5'))
        ->assertStatus(400);

    expect($this->order->refresh()->status)->toBe('pending')
        ->and(PayPalWebhookEvent::count())->toBe(0);
});

it('refuses to process anything while no webhook id is configured', function () {
    config()->set('paypal.sandbox.webhook_id', '');

    $this->postJson('/webhooks/paypal', captureEvent($this->order, 'WH-EVT-6'))
        ->assertStatus(503);

    expect(PayPalWebhookEvent::count())->toBe(0);
});
