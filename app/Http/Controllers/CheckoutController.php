<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Vehicle;
use App\Services\CheckoutFlow;
use App\Settings\PaymentSettings;
use App\Support\PayPalGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutFlow $flow) {}

    /**
     * GET — the same destination/shipping/CIF confirmation step bank
     * transfer uses. PayPal is only reached after the buyer confirms.
     */
    public function show(string $slug): View|RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        if ($error = $this->flow->eligibilityError($vehicle)) {
            return redirect()->route('vehicles.show', $vehicle->slug)->withErrors(['vehicle' => $error]);
        }
        if (! app(PaymentSettings::class)->paypal_enabled || ! $this->paypalConfigured()) {
            return redirect()->route('vehicles.show', $vehicle->slug)
                ->withErrors(['paypal' => 'Online checkout is not yet enabled. Please contact us.']);
        }

        return view('checkout.details', [
            'vehicle' => $vehicle,
            'countries' => $this->flow->countries(),
            'user' => auth()->user(),
            'heading' => 'PayPal checkout',
            'action' => route('checkout.paypal.place', $vehicle->slug),
            'confirmText' => 'I confirm the destination port, address and amount above. On the next step you will be taken to PayPal to pay this amount securely.',
            'submitLabel' => 'Continue to PayPal',
        ]);
    }

    /**
     * POST — validate and place the order exactly as bank transfer does,
     * then hand the buyer to PayPal for the actual payment.
     */
    public function place(Request $request, string $slug): RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        if (! app(PaymentSettings::class)->paypal_enabled || ! $this->paypalConfigured()) {
            return back()->withErrors(['paypal' => 'Online checkout is not yet enabled. Please contact us.']);
        }
        if ($this->flow->eligibilityError($vehicle)) {
            return back()->withErrors(['vehicle' => 'This vehicle cannot be checked out automatically — please contact us.']);
        }

        $data = $request->validate($this->flow->rules());

        $port = $this->flow->resolvePort($data);
        if (! $port) {
            return back()->withErrors(['port_id' => 'The selected port does not belong to the chosen country.']);
        }

        $order = $this->flow->createOrder($data, $vehicle, $port, 'paypal');

        return $this->handOffToPayPal($order, $vehicle)
            ->withCookie(cookie('toco_port', (string) $port->id, 60 * 24 * 365));
    }

    /**
     * Legacy entry point: the vehicle page used to POST straight here and
     * jump to PayPal. Cached copies of that page may still exist, so send
     * them into the confirmation step instead of charging immediately.
     */
    public function start(string $slug): RedirectResponse
    {
        return redirect()->route('checkout.paypal.show', $slug);
    }

    /**
     * Create the PayPal order for an already-placed local order and send
     * the buyer to the approval page.
     */
    protected function handOffToPayPal(Order $order, Vehicle $vehicle): RedirectResponse
    {
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->order_no,
                // Echoed back on every capture/refund webhook — this is how
                // PayPalWebhookController finds its way back to this order.
                'custom_id' => $order->order_no,
                'description' => 'Toco Japan — '.$vehicle->title.' (Ref '.$vehicle->ref_no.')',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format((float) $order->amount_usd, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => config('app.name', 'Toco Japan'),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => route('checkout.return', $order),
                'cancel_url' => route('checkout.cancel', $order),
            ],
        ];

        try {
            $response = $this->paypalClient()->createOrder($payload);
        } catch (\Throwable $e) {
            Log::error('PayPal createOrder failed: '.$e->getMessage(), ['order_no' => $order->order_no]);
            $response = [];
        }

        if (! isset($response['id'])) {
            // Keep the order — the buyer can retry payment from the order
            // page rather than re-entering every shipping field.
            return redirect()->route('orders.show', $order)
                ->withErrors(['paypal' => 'Could not start the PayPal payment. Your order was saved — please try again from this page.']);
        }

        $order->paypal_order_id = $response['id'];
        $order->save();

        foreach ($response['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return redirect()->away($link['href']);
            }
        }

        return redirect()->route('orders.show', $order)
            ->withErrors(['paypal' => 'PayPal did not return an approval URL. Your order was saved — please try again from this page.']);
    }

    public function return(Request $request, Order $order): RedirectResponse
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        if (! $this->paypalConfigured()) {
            return redirect()->route('orders.show', $order);
        }

        // The webhook may already have captured this while the buyer was
        // being redirected. Capturing twice is an error at PayPal, so if the
        // order is settled there is nothing left to do here.
        if ($order->isPaid() || ! $order->paypal_order_id) {
            return redirect()->route('orders.show', $order);
        }

        try {
            $capture = $this->paypalClient()->capturePaymentOrder($order->paypal_order_id);
        } catch (\Throwable $e) {
            // Not fatal for the buyer: CHECKOUT.ORDER.APPROVED will arrive at
            // the webhook and capture there.
            Log::warning('PayPal capture on return failed: '.$e->getMessage(), ['order_no' => $order->order_no]);

            return redirect()->route('orders.show', $order);
        }

        $captureId = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
        $status = $capture['status'] ?? null;

        $order->payment_payload = $capture;
        if ($status === 'COMPLETED') {
            $order->paypal_capture_id = $captureId;
        }
        $order->save();

        if ($status === 'COMPLETED') {
            $order->transitionTo('paid');
        }

        return redirect()->route('orders.show', $order);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        if ($order->status === 'pending') {
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->save();
        }

        return redirect()->route('vehicles.show', $order->vehicle->slug);
    }

    protected function paypalConfigured(): bool
    {
        return PayPalGateway::configured();
    }

    protected function paypalClient(): PayPalClient
    {
        return PayPalGateway::client();
    }
}
