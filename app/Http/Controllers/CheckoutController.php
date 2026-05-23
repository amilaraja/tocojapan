<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class CheckoutController extends Controller
{
    public function start(string $slug): RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        $effectivePrice = $vehicle->effectivePriceFob();
        if ($effectivePrice === null || $effectivePrice <= 0) {
            return back()->withErrors(['vehicle' => 'This vehicle is sold on request — please send an inquiry.']);
        }

        if (! $this->paypalConfigured()) {
            return back()->withErrors(['paypal' => 'Online checkout is not yet enabled. Please contact us.']);
        }

        $order = Order::create([
            'user_id' => Auth::id(),
            'vehicle_id' => $vehicle->id,
            'amount_usd' => $effectivePrice,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_provider' => 'paypal',
        ]);

        $paypal = $this->paypalClient();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->order_no,
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

        $response = $paypal->createOrder($payload);

        if (! isset($response['id'])) {
            $order->delete();

            return back()->withErrors(['paypal' => 'Could not start the payment. Please try again.']);
        }

        $order->paypal_order_id = $response['id'];
        $order->save();

        foreach ($response['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return redirect()->away($link['href']);
            }
        }

        return back()->withErrors(['paypal' => 'PayPal did not return an approval URL.']);
    }

    public function return(Request $request, Order $order): RedirectResponse
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        if (! $this->paypalConfigured()) {
            return redirect()->route('orders.show', $order);
        }

        $paypal = $this->paypalClient();
        $capture = $paypal->capturePaymentOrder($order->paypal_order_id);

        $captureId = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
        $status = $capture['status'] ?? null;

        $order->payment_payload = $capture;
        if ($status === 'COMPLETED') {
            $order->status = 'paid';
            $order->paid_at = now();
            $order->paypal_capture_id = $captureId;
        }
        $order->save();

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
        $mode = config('paypal.mode', 'sandbox');
        $cfg = config("paypal.{$mode}");

        return ! empty($cfg['client_id']) && ! empty($cfg['client_secret']);
    }

    protected function paypalClient(): PayPalClient
    {
        $paypal = new PayPalClient();
        $paypal->setApiCredentials(config('paypal'));
        $paypal->setAccessToken($paypal->getAccessToken());

        return $paypal;
    }
}
