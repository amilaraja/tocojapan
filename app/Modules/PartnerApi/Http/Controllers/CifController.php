<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Models\Port;
use App\Modules\PartnerApi\Domain\Stock\PartnerPricing;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Support\PartnerApiException;
use App\Modules\PartnerApi\Support\StrictInput;
use App\Services\CifCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** POST /cif: CIF estimate on the partner's own price (FR-CIF-01). */
class CifController
{
    public function __invoke(Request $request, CifCalculator $calculator): JsonResponse
    {
        StrictInput::only($request, ['stock_no', 'port_id']);
        $data = Validator::make($request->all(), [
            'stock_no' => ['required', 'string', 'max:40'],
            'port_id' => ['required', 'integer'],
        ])->validate();

        $partner = $request->attributes->get(AuthenticatePartner::TOKEN)->client->partner;

        $vehicle = PartnerScope::listable($partner)->where('vehicles.stock_no', $data['stock_no'])->first();
        if (! $vehicle) {
            throw new PartnerApiException(404, 'not_found', 'Not found.');
        }

        $port = Port::with('country')->where('is_active', true)->find($data['port_id']);
        if (! $port || ! $port->country?->is_active) {
            throw new PartnerApiException(422, 'unknown_port', 'That port is not available.', 'Use an id from GET /catalog/ports.');
        }

        $price = PartnerPricing::price($vehicle, $partner);
        if ($price['amount'] === null || ! $vehicle->m3 || (float) $vehicle->m3 <= 0) {
            throw new PartnerApiException(422, 'cif_unavailable', 'A CIF estimate is not available for this vehicle.',
                $price['amount'] === null ? 'The price is on request.' : 'The shipping volume is not known yet.');
        }

        $cif = $calculator->calculate(priceFob: (float) $price['amount'], m3: (float) $vehicle->m3, port: $port, currency: $price['currency']);

        return new JsonResponse(['data' => [
            'stock_no' => $vehicle->stock_no,
            'port' => ['id' => $port->id, 'name' => $port->name, 'country' => $port->country->iso2],
            'fob' => $cif['price_fob'],
            'freight' => $cif['freight'],
            'insurance' => $cif['insurance'],
            'cif' => $cif['cif_total'],
            'currency' => $price['currency'],
            'note' => 'Estimate only. Inspection fees and import duties are not included.',
        ]]);
    }
}
