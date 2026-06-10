<?php

/**
 * One-off enrichment importer: japanesecartrade.com scrape -> import_regulations.
 *
 * - Resolves each scraped file to an existing Country (by slug + alias map),
 *   creating 8 dependent-territory rows that we don't yet carry.
 * - OVERWRITES: deletes a country's existing active regulations, then inserts
 *   one fresh regulation built from the scrape (per the chosen import scope).
 * - Skips scrape error pages and unreliable tax/duty figures.
 *
 * Idempotent: re-running replaces the same countries' regulations cleanly.
 * Run: php scripts/import_regulations_from_scrape.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Country;
use App\Models\ImportRegulation;
use Illuminate\Support\Str;

$DIR = '/home/tocojapan.com/designs/import_reg_output';

/** Dependent territories we don't carry yet — created so the scrape can attach. */
$NEW_TERRITORIES = [
    'hong-kong'        => ['Hong Kong', 'HK', 'Asia'],
    'macau'            => ['Macau', 'MO', 'Asia'],
    'puerto-rico'      => ['Puerto Rico', 'PR', 'Caribbean'],
    'cook-islands'     => ['Cook Islands', 'CK', 'Oceania'],
    'falkland-islands' => ['Falkland Islands', 'FK', 'America'],
    'st-helena'        => ['St Helena', 'SH', 'Africa'],
    'tokelau-island'   => ['Tokelau', 'TK', 'Oceania'],
    'turks-and-caicos' => ['Turks and Caicos', 'TC', 'Caribbean'],
];

/** scrape-slug => our country slug, where they differ. */
$ALIAS = [
    'uae' => 'united-arab-emirates',
    'usa' => 'united-states',
    'russian-federation' => 'russia',
    'caledonia' => 'new-caledonia',
    'east-timor' => 'timor-leste',
    'philippines-liberia' => 'philippines',
    'cayman-island' => 'cayman-islands',
    'saint-vincent' => 'saint-vincent-and-the-grenadines',
    'swaziland' => 'eswatini',
    'dominican' => 'dominica',
];

// --- create missing territories ---
$created = [];
foreach ($NEW_TERRITORIES as $slug => [$name, $iso2, $region]) {
    $c = Country::firstOrCreate(
        ['slug' => $slug],
        ['name' => $name, 'iso2' => $iso2, 'region' => $region, 'is_active' => true, 'sort_order' => 0]
    );
    if ($c->wasRecentlyCreated) {
        $created[] = $name;
    }
}

// --- lookups ---
$bySlug = [];
$byName = [];
foreach (Country::all() as $c) {
    if ($c->slug) {
        $bySlug[strtolower($c->slug)] = $c;
    }
    $byName[strtolower(trim($c->name))] = $c;
}

$clean = fn (?string $s, int $max) => $s === null ? null
    : (Str::limit(trim(preg_replace('/\s+/', ' ', $s)), $max, '') ?: null);

$steerMap = fn (?string $s) => match (strtolower((string) $s)) {
    'right' => 'rhd_only',
    'left' => 'lhd_only',
    default => null,    // "both" / unknown => any
};

$added = [];
$overwritten = [];
$unmatched = [];
$skipped = [];

foreach (glob("$DIR/import_reg_*.json") as $file) {
    $slug = preg_replace('/^import_reg_/', '', basename($file, '.json'));
    $d = json_decode(file_get_contents($file), true);
    $title = $d['page_title'] ?? '';
    $ef = $d['extracted_fields'] ?? [];

    if (! $ef || stripos($title, 'Error establishing') !== false || stripos($title, 'database connection') !== false) {
        $skipped[] = $slug;
        continue;
    }

    $look = $ALIAS[$slug] ?? $slug;
    $country = $bySlug[strtolower($look)]
        ?? $byName[strtolower(str_replace('-', ' ', $look))]
        ?? null;
    if (! $country) {
        $unmatched[] = $slug;
        continue;
    }

    // --- map fields ---
    $ports = $ef['destination_ports'] ?? [];
    $portText = is_array($ports) ? implode(', ', array_filter(array_map('trim', $ports))) : null;

    $age = $ef['age_restriction_raw'] ?? null;
    if (! $age && ! empty($ef['no_age_restriction'])) {
        $age = 'No age limit';
    }

    $maxAge = $ef['vehicle_age_max_years'] ?? null;
    $maxAge = (is_numeric($maxAge) && $maxAge >= 1 && $maxAge <= 40) ? (int) $maxAge : null;

    $agency = trim((string) ($ef['inspection_agency'] ?? ''));
    $inspectionReq = ! empty($ef['inspection_required']);
    if ($agency !== '') {
        $inspection = $agency.($inspectionReq ? ' (compulsory)' : '');
    } elseif ($inspectionReq) {
        $inspection = 'Required (pre-shipment)';
    } else {
        $inspection = null;
    }

    $other = [];
    if (! empty($ef['emission_standard'])) {
        $other[] = $ef['emission_standard'];
    }
    if (! empty($ef['mileage_limit_km']) && is_numeric($ef['mileage_limit_km'])) {
        $other[] = 'Max mileage '.number_format((int) $ef['mileage_limit_km']).' km';
    }
    if (! empty($ef['vehicle_age_min_years']) && is_numeric($ef['vehicle_age_min_years'])) {
        $other[] = 'Min age '.(int) $ef['vehicle_age_min_years'].' yr';
    }

    $payload = [
        'short_description' => $clean($portText ?: null, 255),
        'year_restriction' => $clean($age, 255),
        'year_max_age' => $maxAge,
        'steering_restriction' => $steerMap($ef['steering_side_allowed'] ?? null),
        'inspection' => $clean($inspection, 120),
        'other_restrictions' => $clean($other ? implode(', ', $other) : null, 255),
        'time_of_shipment' => $clean($ef['shipment_method'] ?? null, 120),
        'comments' => null,
        'is_active' => true,
        'sort_order' => 0,
    ];

    $had = $country->importRegulations()->where('is_active', true)->exists();
    // Overwrite: clear existing active regs for this country, then insert fresh.
    $country->importRegulations()->delete();
    $reg = new ImportRegulation($payload);
    $reg->country_id = $country->id;
    $reg->save();

    $had ? $overwritten[] = $country->name : $added[] = $country->name;
}

sort($added);
sort($overwritten);
sort($unmatched);
sort($skipped);

echo "Territories created: ".count($created)." (".implode(', ', $created).")\n";
echo "Regulations ADDED  : ".count($added)."\n";
echo "Regulations OVERWRITTEN: ".count($overwritten)." (".implode(', ', $overwritten).")\n";
echo "Unmatched (skipped): ".count($unmatched)." (".implode(', ', $unmatched).")\n";
echo "Scrape errors skipped: ".count($skipped)." (".implode(', ', $skipped).")\n";
echo "\nTotal countries with active regs now: ".Country::whereHas('importRegulations', fn ($q) => $q->where('is_active', true))->count()."\n";
