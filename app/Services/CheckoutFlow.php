<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Order;
use App\Models\Port;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The shared "collect destination + shipping details, confirm the CIF
 * total, place the order" step. Bank transfer and PayPal both run through
 * it so the buyer sees the same journey and is quoted the same amount;
 * only the final step differs (show bank details vs. hand off to PayPal).
 */
class CheckoutFlow
{
    public function __construct(private CifCalculator $calculator) {}

    /** Null when the vehicle can be checked out, otherwise the reason it cannot. */
    public function eligibilityError(Vehicle $vehicle): ?string
    {
        if (! $vehicle->effectivePriceFob() || $vehicle->effectivePriceFob() <= 0) {
            return 'This vehicle is priced on request — please send an inquiry.';
        }
        if (! $vehicle->m3 || $vehicle->m3 <= 0) {
            return 'Shipping volume (m³) is missing on this vehicle — please contact us.';
        }

        return null;
    }

    /** Active countries with their active ports, for the destination picker. */
    public function countries(): Collection
    {
        return Country::query()
            ->where('is_active', true)
            ->with(['ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
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
        ];
    }

    /** The port the buyer picked, or null if it does not belong to the chosen country. */
    public function resolvePort(array $data): ?Port
    {
        $port = Port::with('country')->findOrFail($data['port_id']);

        return (int) $port->country_id === (int) $data['country_id'] ? $port : null;
    }

    /**
     * Create the order with a CIF snapshot taken at this moment. The amount
     * charged is the CIF total, whichever provider is used.
     */
    public function createOrder(array $data, Vehicle $vehicle, Port $port, string $provider): Order
    {
        $cif = $this->calculator->calculate(
            priceFob: (float) $vehicle->effectivePriceFob(),
            m3: (float) $vehicle->m3,
            port: $port,
        );

        return Order::create([
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
            'payment_provider' => $provider,
        ]);
    }
}
