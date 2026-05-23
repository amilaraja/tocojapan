<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CifCalculateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Port;
use App\Models\Vehicle;
use App\Services\CifCalculator;
use Illuminate\Http\JsonResponse;

class CifController extends Controller
{
    public function __construct(private readonly CifCalculator $calculator) {}

    public function calculate(CifCalculateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $port = Port::with('country')->findOrFail($data['port_id']);

        $currency = $data['currency'] ?? null;
        $vehicleMeta = null;

        if (! empty($data['vehicle_slug'])) {
            $vehicle = Vehicle::query()
                ->published()
                ->where('slug', $data['vehicle_slug'])
                ->firstOrFail();

            $effective = $vehicle->effectivePriceFob();
            if ($effective === null || $effective == 0.0) {
                return ApiResponse::error('This vehicle is priced on request — CIF cannot be auto-calculated.', 422, [
                    'vehicle_slug' => ['Price is on request.'],
                ]);
            }
            if ($vehicle->m3 === null || $vehicle->m3 == 0.0) {
                return ApiResponse::error('This vehicle has no shipping volume (m³) recorded.', 422, [
                    'vehicle_slug' => ['Missing m³.'],
                ]);
            }

            $priceFob = $effective;
            $m3 = (float) $vehicle->m3;
            $currency = $currency ?? $vehicle->currency;
            $vehicleMeta = [
                'slug' => $vehicle->slug,
                'ref_no' => $vehicle->ref_no,
                'title' => $vehicle->title,
            ];
        } else {
            $priceFob = (float) $data['price_fob'];
            $m3 = (float) $data['m3'];
        }

        $breakdown = $this->calculator->calculate(
            priceFob: $priceFob,
            m3: $m3,
            port: $port,
            currency: $currency,
        );

        return ApiResponse::ok($breakdown, ['vehicle' => $vehicleMeta]);
    }
}
