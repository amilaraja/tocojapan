<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Order;
use App\Models\Port;
use App\Models\Vehicle;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use App\Services\Holds\VehicleUnavailable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The shared "collect destination + shipping details, confirm the CIF
 * total, place the order" step. Bank transfer and PayPal both run through
 * it so the buyer sees the same journey and is quoted the same amount;
 * only the final step differs (show bank details vs. hand off to PayPal).
 */
class CheckoutFlow
{
    public function __construct(private CifCalculator $calculator, private HoldService $holds) {}

    /**
     * Find a vehicle for checkout: published, or reserved by this buyer
     * (they may switch payment method). Null when someone else holds it.
     */
    public function checkoutVehicle(string $slug): ?Vehicle
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->whereIn('status', ['published', 'reserved'])->firstOrFail();

        if ($vehicle->isReserved() && ! $this->heldByCurrentBuyer($vehicle)) {
            return null;
        }

        return $vehicle;
    }

    public function heldByCurrentBuyer(Vehicle $vehicle): bool
    {
        $hold = $this->holds->activeHold($vehicle);

        return $hold !== null && Auth::id() !== null && $hold->isWeb() && $hold->user_id === Auth::id();
    }

    /** Null when the vehicle can be checked out, otherwise the reason it cannot. */
    public function eligibilityError(Vehicle $vehicle): ?string
    {
        if (! $vehicle->canCheckoutOnline()) {
            return 'Availability of this partner vehicle is confirmed with a quote — please request a quote.';
        }
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

        $attributes = [
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
        ];

        if (! HoldService::enforced()) {
            return Order::create($attributes);
        }

        // FR-HLD-03: no hold, no payment. The order and its hold stand or fall together.
        return DB::transaction(function () use ($attributes, $vehicle) {
            $order = Order::create($attributes);
            $this->holds->claim($vehicle, HoldClaim::forOrder($order));

            return $order;
        });
    }

    /** Where a buyer who lost the race for a car is sent (UI brief section 7.2). */
    public static function raceLostRedirect(Vehicle $vehicle): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('vehicles.show', $vehicle->slug)->with('vehicle_reserved', true);
    }
}
