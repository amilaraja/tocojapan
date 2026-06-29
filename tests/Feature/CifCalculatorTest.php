<?php

use App\Models\Country;
use App\Models\Port;
use App\Services\CifCalculator;
use App\Settings\CifSettings;

function makePort(): Port
{
    $country = Country::create([
        'iso2' => 'TS',
        'name' => 'Testland',
        'slug' => 'testland',
        'is_active' => true,
    ]);

    return Port::create([
        'country_id' => $country->id,
        'name' => 'Testport',
        'slug' => 'testport',
        'rate_per_m3' => 30.0,
        'is_active' => true,
    ]);
}

it('computes CIF with a flat marine insurance fee', function () {
    $port = makePort();
    $settings = app(CifSettings::class);
    $settings->marine_insurance_usd = 35.0;
    $settings->default_currency = 'USD';
    $settings->price_on_request_default = false;
    $settings->save();

    $calc = new CifCalculator($settings);
    $r = $calc->calculate(priceFob: 5000.00, m3: 12.5, port: $port);

    // freight = 12.5 * 30 = 375
    // insurance = 35 (flat)
    // cif = 5000 + 375 + 35 = 5410
    expect($r['freight'])->toBe(375.00);
    expect($r['insurance'])->toBe(35.00);
    expect($r['cif_total'])->toBe(5410.00);
    expect($r['currency'])->toBe('USD');
    expect($r['port']['country'])->toBe('Testland');
});

it('honours a non-default marine insurance amount', function () {
    $port = makePort();
    $settings = app(CifSettings::class);
    $settings->marine_insurance_usd = 50.0;
    $settings->default_currency = 'USD';
    $settings->price_on_request_default = false;
    $settings->save();

    $calc = new CifCalculator($settings);
    $r = $calc->calculate(priceFob: 5000.00, m3: 12.5, port: $port);

    expect($r['insurance'])->toBe(50.00);
    expect($r['cif_total'])->toBe(5425.00);
});

it('rejects non-positive m3', function () {
    $port = makePort();
    $settings = app(CifSettings::class);
    $calc = new CifCalculator($settings);
    expect(fn () => $calc->calculate(1000.0, 0.0, $port))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects negative price_fob', function () {
    $port = makePort();
    $settings = app(CifSettings::class);
    $calc = new CifCalculator($settings);
    expect(fn () => $calc->calculate(-1.0, 5.0, $port))
        ->toThrow(InvalidArgumentException::class);
});
