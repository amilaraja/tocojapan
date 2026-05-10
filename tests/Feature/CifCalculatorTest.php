<?php

use App\Models\Country;
use App\Models\Port;
use App\Services\CifCalculator;
use App\Settings\CifSettings;

function makePort(?float $insurancePct = null): Port
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
        'insurance_pct' => $insurancePct,
        'is_active' => true,
    ]);
}

it('computes CIF using settings insurance pct when port has none', function () {
    $port = makePort();
    $settings = app(CifSettings::class);
    $settings->insurance_pct = 0.015;
    $settings->default_currency = 'USD';
    $settings->price_on_request_default = false;
    $settings->save();

    $calc = new CifCalculator($settings);
    $r = $calc->calculate(priceFob: 5000.00, m3: 12.5, port: $port);

    // freight = 12.5 * 30 = 375
    // insurance = (5000 + 375) * 0.015 = 80.625 → 80.63
    // cif = 5000 + 375 + 80.63 = 5455.63
    expect($r['freight'])->toBe(375.00);
    expect($r['insurance'])->toBe(80.63);
    expect($r['cif_total'])->toBe(5455.63);
    expect($r['insurance_pct'])->toBe(0.015);
    expect($r['currency'])->toBe('USD');
    expect($r['port']['country'])->toBe('Testland');
});

it('uses port-level insurance pct override when present', function () {
    $port = makePort(insurancePct: 0.025);
    $settings = app(CifSettings::class);
    $settings->insurance_pct = 0.015;
    $settings->default_currency = 'USD';
    $settings->price_on_request_default = false;
    $settings->save();

    $calc = new CifCalculator($settings);
    $r = $calc->calculate(priceFob: 5000.00, m3: 12.5, port: $port);

    // insurance = (5000 + 375) * 0.025 = 134.375 → 134.38
    expect($r['insurance'])->toBe(134.38);
    expect($r['insurance_pct'])->toBe(0.025);
});

it('uses explicit override above port and settings', function () {
    $port = makePort(insurancePct: 0.025);
    $settings = app(CifSettings::class);
    $settings->insurance_pct = 0.015;
    $settings->default_currency = 'USD';
    $settings->price_on_request_default = false;
    $settings->save();

    $calc = new CifCalculator($settings);
    $r = $calc->calculate(priceFob: 5000.00, m3: 12.5, port: $port, insurancePctOverride: 0.05);

    // insurance = (5000 + 375) * 0.05 = 268.75
    expect($r['insurance'])->toBe(268.75);
    expect($r['insurance_pct'])->toBe(0.05);
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
