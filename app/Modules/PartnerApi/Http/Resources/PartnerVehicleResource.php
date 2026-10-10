<?php

namespace App\Modules\PartnerApi\Http\Resources;

use App\Models\Vehicle;
use App\Modules\PartnerApi\Domain\Images\PartnerImages;
use App\Modules\PartnerApi\Domain\Stock\PartnerPricing;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Models\PartnerClient;

/**
 * The ONLY shape a vehicle leaves Toco in for partners (FR-STK-03). Every
 * field is listed by hand; supplier cost, supplier ids, margins, notes and
 * admin data are never read here. PartnerVehicleContractTest fails the build
 * if a field appears that is not in STANDARD / EXTENDED.
 */
class PartnerVehicleResource
{
    public const STANDARD = [
        'stock_no', 'title', 'status', 'make', 'model', 'body_type', 'year', 'registration', 'manufacture',
        'mileage_km', 'engine_cc', 'fuel', 'transmission', 'drive', 'steering', 'exterior_color', 'doors',
        'seats', 'grade', 'm3', 'price', 'photos', 'image_mode_applied', 'detail_url', 'updated_at',
    ];

    public const EXTENDED = ['chassis_number', 'model_code', 'dimensions_cm', 'interior_color', 'features', 'description'];

    /** @return array<string, mixed> */
    public static function make(Vehicle $vehicle, PartnerClient $client): array
    {
        $partner = $client->partner;

        $data = [
            'stock_no' => $vehicle->stock_no,
            'title' => $vehicle->title,
            'status' => PartnerScope::status($vehicle),
            'make' => $vehicle->make ? ['slug' => $vehicle->make->slug, 'name' => $vehicle->make->name] : null,
            'model' => $vehicle->vehicleModel ? ['slug' => $vehicle->vehicleModel->slug, 'name' => $vehicle->vehicleModel->name] : null,
            'body_type' => $vehicle->bodyType ? ['slug' => $vehicle->bodyType->slug, 'name' => $vehicle->bodyType->name] : null,
            'year' => $vehicle->year_first_reg ?? $vehicle->manufacture_year,
            'registration' => ['year' => $vehicle->year_first_reg, 'month' => $vehicle->registration_month],
            'manufacture' => ['year' => $vehicle->manufacture_year, 'month' => $vehicle->manufacture_month],
            'mileage_km' => $vehicle->mileage_km,
            'engine_cc' => $vehicle->engine_cc,
            'fuel' => $vehicle->fuel,
            'transmission' => $vehicle->transmission,
            'drive' => $vehicle->drive,
            'steering' => $vehicle->steering_side,
            'exterior_color' => $vehicle->exterior_color,
            'doors' => $vehicle->doors,
            'seats' => $vehicle->seats,
            'grade' => $vehicle->grade,
            'm3' => $vehicle->m3 !== null ? (float) $vehicle->m3 : null,
            'price' => PartnerPricing::price($vehicle, $partner),
            'photos' => PartnerImages::forVehicle($vehicle, $client),
            'image_mode_applied' => PartnerImages::MODE_APPLIED,
            'detail_url' => rtrim((string) config('app.url'), '/').'/vehicles/'.$vehicle->slug.'?ref='.$partner->attribution_code,
            'updated_at' => $vehicle->updated_at?->toIso8601ZuluString(),
        ];

        if ($partner->field_set === 'extended') {
            $data += [
                'chassis_number' => $vehicle->chassis_number,
                'model_code' => $vehicle->model_code,
                'dimensions_cm' => ['length' => $vehicle->length_cm, 'width' => $vehicle->width_cm, 'height' => $vehicle->height_cm],
                'interior_color' => $vehicle->interior_color,
                'features' => array_values((array) ($vehicle->features ?? [])),
                'description' => $vehicle->description !== null ? trim(strip_tags((string) $vehicle->description)) : null,
            ];
        }

        return $data;
    }

    /** A vehicle that left the partner's view (FR-SYNC-01). */
    public static function tombstone(Vehicle $vehicle): array
    {
        return [
            'stock_no' => $vehicle->stock_no,
            'status' => 'removed',
            'updated_at' => ($vehicle->deleted_at ?? $vehicle->updated_at)?->toIso8601ZuluString(),
        ];
    }
}
