<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Order;
use App\Models\Port;
use App\Models\Vehicle;
use App\Services\CifCalculator;
use App\Settings\PaymentSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankTransferController extends Controller
{
    /**
     * GET — show the shipping + CIF confirmation page before placing the order.
     */
    public function show(string $slug): View|RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        if (! $vehicle->effectivePriceFob() || $vehicle->effectivePriceFob() <= 0) {
            return redirect()->route('vehicles.show', $vehicle->slug)
                ->withErrors(['vehicle' => 'This vehicle is priced on request — please send an inquiry.']);
        }
        if (! $vehicle->m3 || $vehicle->m3 <= 0) {
            return redirect()->route('vehicles.show', $vehicle->slug)
                ->withErrors(['vehicle' => 'Shipping volume (m³) is missing on this vehicle — please contact us.']);
        }
        if (! app(PaymentSettings::class)->bank_transfer_enabled) {
            return redirect()->route('vehicles.show', $vehicle->slug)
                ->withErrors(['payment' => 'Bank transfer checkout is not enabled.']);
        }

        $countries = Country::query()
            ->where('is_active', true)
            ->with(['ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $user = Auth::user();

        return view('checkout.bank', [
            'vehicle' => $vehicle,
            'countries' => $countries,
            'user' => $user,
        ]);
    }

    /**
     * POST — validate, compute CIF snapshot, create the order, redirect to it.
     */
    public function store(Request $request, string $slug, CifCalculator $calculator): RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        if (! app(PaymentSettings::class)->bank_transfer_enabled) {
            return back()->withErrors(['payment' => 'Bank transfer checkout is not enabled.']);
        }
        if (! $vehicle->effectivePriceFob() || $vehicle->effectivePriceFob() <= 0 || ! $vehicle->m3 || $vehicle->m3 <= 0) {
            return back()->withErrors(['vehicle' => 'This vehicle cannot be checked out automatically — please contact us.']);
        }

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'port_id' => ['required', 'integer', 'exists:ports,id'],
            'ship_to_name' => ['required', 'string', 'max:120'],
            'ship_to_phone' => ['required', 'string', 'max:40'],
            'ship_to_address_line1' => ['required', 'string', 'max:255'],
            'ship_to_address_line2' => ['nullable', 'string', 'max:255'],
            'ship_to_city' => ['required', 'string', 'max:80'],
            'ship_to_state' => ['nullable', 'string', 'max:80'],
            'ship_to_postcode' => ['nullable', 'string', 'max:20'],
            'confirm' => ['accepted'],
        ]);

        $port = Port::with('country')->findOrFail($data['port_id']);
        if ((int) $port->country_id !== (int) $data['country_id']) {
            return back()->withErrors(['port_id' => 'The selected port does not belong to the chosen country.']);
        }

        $cif = $calculator->calculate(
            priceFob: (float) $vehicle->effectivePriceFob(),
            m3: (float) $vehicle->m3,
            port: $port,
        );

        $order = Order::create([
            'user_id' => Auth::id(),
            'vehicle_id' => $vehicle->id,
            'dest_country_id' => $data['country_id'],
            'dest_port_id' => $data['port_id'],
            'ship_to_name' => $data['ship_to_name'],
            'ship_to_phone' => $data['ship_to_phone'],
            'ship_to_address_line1' => $data['ship_to_address_line1'],
            'ship_to_address_line2' => $data['ship_to_address_line2'] ?? null,
            'ship_to_city' => $data['ship_to_city'],
            'ship_to_state' => $data['ship_to_state'] ?? null,
            'ship_to_postcode' => $data['ship_to_postcode'] ?? null,
            'amount_usd' => $cif['cif_total'],
            'cif_freight' => $cif['freight'],
            'cif_insurance' => $cif['insurance'],
            'cif_total' => $cif['cif_total'],
            'currency' => 'USD',
            'status' => 'pending',
            'payment_provider' => 'bank_transfer',
        ]);

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order created. Please use the bank details below to complete payment.')
            ->withCookie(cookie('toco_port', (string) $port->id, 60 * 24 * 365));
    }
}
