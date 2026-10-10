<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Port;
use App\Models\Vehicle;
use App\Models\VehicleOption;
use App\Services\CifCalculator;
use App\Settings\CifSettings;
use Illuminate\Support\Collection;

/**
 * Countries + ports for the CIF calculators, with the import regulation that
 * applies to each port (a port-specific rule wins over a country-wide one).
 * Shared by the vehicle detail page and the listing quick-view modal
 * (which loads it lazily from /cif/destinations.json).
 */
class CifDestinations
{
    /** Active countries with their active ports + regulations, as the detail page loads them. */
    public static function countries(): Collection
    {
        return Country::query()
            ->where('is_active', true)
            ->with([
                'ports' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
                'importRegulations' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'importRegulations.ports',
            ])
            ->orderBy('name')
            ->get();
    }

    public static function payload(?Collection $countries = null): array
    {
        return ($countries ?? self::countries())->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'iso2' => $c->iso2,
            'pre_inspection_required' => (bool) $c->pre_inspection_required,
            'ports' => $c->ports->map(function ($p) use ($c) {
                $reg = $c->importRegulations->first(fn ($r) => $r->ports->contains('id', $p->id))
                    ?: $c->importRegulations->first(fn ($r) => $r->ports->isEmpty());

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'rate_per_m3' => (float) $p->rate_per_m3,
                    'regulation' => $reg ? [
                        'description' => $reg->short_description,
                        'age_limit' => $reg->year_restriction,
                        'shipment_time' => $reg->time_of_shipment,
                        'notes' => $reg->comments,
                        'lc' => $reg->allowsLc(),
                    ] : null,
                ];
            })->all(),
        ])->all();
    }

    /** Whether a vehicle can be CIF-estimated at all (priced, has a shipping volume). */
    public static function calculable(Vehicle $vehicle): bool
    {
        return ! $vehicle->price_on_request && $vehicle->m3 !== null && (float) $vehicle->m3 > 0;
    }

    /**
     * Config for the cifCalc() Alpine factory (partials/cif-calc-script),
     * minus the countries list, which callers add inline or as a URL.
     *
     * initialCif is the CIF for the visitor's saved destination port,
     * computed server-side; once they recalculate, Alpine swaps to the live
     * grandTotal so both numbers always tally. Includes the Pre-inspection
     * Fee when the saved destination country mandates it (e.g. Sri Lanka).
     */
    public static function calcConfig(Vehicle $vehicle, ?Port $destPort): array
    {
        $settings = app(CifSettings::class);

        $initialCif = 0.0;
        if (! $vehicle->price_on_request && $destPort && $vehicle->m3 > 0 && (float) $vehicle->effectivePriceFob() > 0) {
            $initialCif = (float) app(CifCalculator::class)->calculate(
                priceFob: (float) $vehicle->effectivePriceFob(),
                m3: (float) $vehicle->m3,
                port: $destPort,
            )['cif_total'];
            if ($destPort->country?->pre_inspection_required) {
                $initialCif += (float) $settings->pre_inspection_fee_usd;
            }
        }

        return [
            'preCountryId' => $destPort?->country_id,
            'prePortId' => $destPort?->id,
            'slug' => $vehicle->slug,
            // CIF add-ons + global options list (CR-2026-05-28).
            'options' => VehicleOption::query()
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'name', 'price', 'tooltip'])
                ->map(fn ($o) => ['id' => (int) $o->id, 'name' => $o->name, 'price' => $o->price === null ? null : (float) $o->price, 'tooltip' => $o->tooltip])
                ->all(),
            'marineInsuranceFee' => (float) $settings->marine_insurance_usd,
            'maintenanceFee' => (float) $settings->maintenance_package_usd,
            'preInspectionFee' => (float) $settings->pre_inspection_fee_usd,
            'initialCif' => $initialCif,
            'initialPortName' => $destPort?->name,
        ];
    }
}
