<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Vehicle;
use App\Settings\PaymentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BankTransferController extends Controller
{
    public function start(string $slug): RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        if ($vehicle->price_on_request || ! $vehicle->price_fob || $vehicle->price_fob <= 0) {
            return back()->withErrors(['vehicle' => 'This vehicle is sold on request — please send an inquiry.']);
        }

        if (! app(PaymentSettings::class)->bank_transfer_enabled) {
            return back()->withErrors(['payment' => 'Bank transfer checkout is not enabled.']);
        }

        $order = Order::create([
            'user_id' => Auth::id(),
            'vehicle_id' => $vehicle->id,
            'amount_usd' => $vehicle->price_fob,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_provider' => 'bank_transfer',
        ]);

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order created. Please use the bank details below to complete payment.');
    }
}
