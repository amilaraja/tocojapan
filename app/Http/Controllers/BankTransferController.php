<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\CheckoutFlow;
use App\Settings\PaymentSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BankTransferController extends Controller
{
    public function __construct(private CheckoutFlow $flow) {}

    /**
     * GET — show the shipping + CIF confirmation page before placing the order.
     */
    public function show(string $slug): View|RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        if ($error = $this->flow->eligibilityError($vehicle)) {
            return redirect()->route('vehicles.show', $vehicle->slug)->withErrors(['vehicle' => $error]);
        }
        if (! app(PaymentSettings::class)->bank_transfer_enabled) {
            return redirect()->route('vehicles.show', $vehicle->slug)
                ->withErrors(['payment' => 'Bank transfer checkout is not enabled.']);
        }

        return view('checkout.details', [
            'vehicle' => $vehicle,
            'countries' => $this->flow->countries(),
            'user' => auth()->user(),
            'heading' => 'Bank-transfer checkout',
            'action' => route('checkout.bank.store', $vehicle->slug),
            'confirmText' => 'I confirm the destination port, address and amount above and want to place this order. After confirmation, bank transfer instructions will be shown.',
            'submitLabel' => 'Place order',
        ]);
    }

    /**
     * POST — validate, compute CIF snapshot, create the order, redirect to it.
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        if (! app(PaymentSettings::class)->bank_transfer_enabled) {
            return back()->withErrors(['payment' => 'Bank transfer checkout is not enabled.']);
        }
        if ($this->flow->eligibilityError($vehicle)) {
            return back()->withErrors(['vehicle' => 'This vehicle cannot be checked out automatically — please contact us.']);
        }

        $data = $request->validate($this->flow->rules());

        $port = $this->flow->resolvePort($data);
        if (! $port) {
            return back()->withErrors(['port_id' => 'The selected port does not belong to the chosen country.']);
        }

        $order = $this->flow->createOrder($data, $vehicle, $port, 'bank_transfer');

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order created. Please use the bank details below to complete payment.')
            ->withCookie(cookie('toco_port', (string) $port->id, 60 * 24 * 365));
    }
}
