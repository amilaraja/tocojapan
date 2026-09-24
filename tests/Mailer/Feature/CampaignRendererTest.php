<?php

use App\Modules\Mailer\Domain\Campaigns\CampaignRenderer;
use App\Modules\Mailer\Domain\Campaigns\UtmTagger;
use App\Modules\Mailer\Domain\Vehicles\VehicleDTO;
use App\Modules\Mailer\Models\Banner;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function sampleVehicle(int $i, array $over = []): VehicleDTO
{
    return VehicleDTO::fromArray(array_merge([
        'id' => $i, 'stockRef' => sprintf('E%05d', 2000 + $i), 'title' => "2019 TOYOTA VITZ F {$i}",
        'registrationYear' => 2019, 'mileageKm' => 50002, 'transmission' => 'Automatic',
        'priceFob' => 5900, 'previousPrice' => null, 'badge' => 'new', 'status' => 'available',
        'photoPath' => null, 'url' => "https://tocojapan.com/vehicles/vehicle-{$i}",
    ], $over));
}

function sampleCampaign(array $over = []): Campaign
{
    return new Campaign(array_merge([
        'name' => 'Test', 'slug' => 'this-weeks-picks-2026-09-24', 'subject' => 'Picks',
        'preview_text' => 'Hot deals this week', 'kicker' => 'THIS WEEK’S PICKS',
        'headline' => 'Fresh stock', 'intro' => 'Inspected in Japan.',
    ], $over));
}

function renderEmail(int $count, array $campaign = [], array $vehicle = []): string
{
    $vehicles = collect(range(1, $count))->map(fn ($i) => sampleVehicle($i, $vehicle));

    return app(CampaignRenderer::class)->renderWith(sampleCampaign($campaign), $vehicles);
}

it('has no script, external CSS or font links and a 600px main table (TOC-TPL-001)', function () {
    $html = renderEmail(6);

    expect($html)->not->toMatch('/<script\b/i')
        ->not->toMatch('/<link\b/i')
        ->not->toMatch('/@import/i')
        ->not->toMatch('/<form\b/i')
        ->toMatch('/<table[^>]*class="wrap"[^>]*width="600"/')
        ->toContain('Arial, Helvetica, sans-serif');
});

it('renders all sections in the fixed order with both Brevo tags literal (TOC-TPL-002)', function () {
    $html = renderEmail(6, ['banner_id' => null]);
    $campaign = sampleCampaign();
    $campaign->setRelation('banner', new Banner(['path' => 'email-assets/banners/b.jpg', 'alt_text' => 'Hot deals', 'link_url' => 'https://tocojapan.com/vehicles']));
    $html = app(CampaignRenderer::class)->renderWith($campaign, collect([sampleVehicle(1), sampleVehicle(2)]));

    preg_match_all('/<!-- SECTION:([a-z]+) -->/', $html, $m);

    expect($m[1])->toBe(['preheader', 'topbar', 'header', 'banner', 'intro', 'vehicles', 'cta', 'fraud', 'footer'])
        ->and($html)->toContain('href="{{ mirror }}"')
        ->and($html)->toContain('href="{{ unsubscribe }}"');
});

it('shows photo, badge, ref, meta, title, price and a View vehicle button per card (TOC-TPL-003)', function () {
    $html = renderEmail(2, [], ['badge' => 'hot_deal', 'priceFob' => 2650, 'previousPrice' => 2750]);

    expect($html)->toContain('HOT DEAL')
        ->toContain('background:#E30613;padding:3px 7px')
        ->toContain('#E02001')
        ->toContain('2019 · 50,002 km · Automatic')
        ->toContain('2019 TOYOTA VITZ F 1')
        ->toMatch('/line-through;">\$2,750</')
        ->toContain('$2,650')
        ->toContain('VIEW VEHICLE');
});

it('lays out 5 vehicles as 2, 2, 1 with the right cell empty (TOC-TPL-004)', function () {
    $html = renderEmail(5);

    preg_match('/<!-- SECTION:vehicles -->(.*)<!-- SECTION:cta -->/s', $html, $grid);
    // Each row starts with its left cell; drop the text before the first one.
    $rows = array_slice(preg_split('/class="col col-l"/', $grid[1]), 1);

    expect($rows)->toHaveCount(3)
        ->and(substr_count($grid[1], 'class="card"'))->toBe(5)
        ->and(substr_count($rows[2], 'class="card"'))->toBe(1)
        ->and(substr_count($grid[1], 'class="col empty"'))->toBe(1)
        ->and($html)->toContain('@media only screen and (max-width:480px)');
});

it('gives every image alt text and a width (TOC-TPL-005)', function () {
    preg_match_all('/<img\b[^>]*>/', renderEmail(3), $imgs);

    expect($imgs[0])->not->toBeEmpty();
    foreach ($imgs[0] as $img) {
        expect($img)->toMatch('/\salt="[^"]+"/')->toMatch('/\swidth="\d+"/');
    }
});

it('keeps a 12-vehicle email under 90 KB (TOC-TPL-006)', function () {
    $html = renderEmail(12, [], ['title' => '2001 SUBARU SAMBAR SUPER CHARGER DIAS CLASSIC 4WD', 'previousPrice' => 9999]);

    expect(strlen($html))->toBeLessThan(90 * 1024);
});

it('tags every tocojapan.com link with the four UTM parameters (TOC-CMP-005)', function () {
    $html = renderEmail(3);

    preg_match_all('/href="([^"]+)"/', $html, $m);
    $site = array_filter(array_map('html_entity_decode', $m[1]), fn ($u) => str_contains((string) parse_url($u, PHP_URL_HOST), 'tocojapan.com'));

    expect($site)->not->toBeEmpty();
    foreach ($site as $url) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        expect($q)->toHaveKeys(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'])
            ->and($q['utm_source'])->toBe('brevo')
            ->and($q['utm_medium'])->toBe('email')
            ->and($q['utm_campaign'])->toBe('this-weeks-picks-2026-09-24');
    }

    expect($html)->toContain('utm_content=E02001')
        ->toContain('utm_content=cta')
        ->not->toContain('data-utm');
});

it('uses footer, fraud text and nav links from settings (TOC-TPL-009)', function () {
    app(MailerSettings::class)->setMany([
        'footer_phone' => '+81 3 1234 5678',
        'fraud_text' => 'Watch out. Only pay our bank.',
        'nav_links' => [['label' => 'STOCK', 'url' => 'https://tocojapan.com/vehicles']],
    ]);

    $html = renderEmail(2);

    expect($html)->toContain('+81 3 1234 5678')
        ->toContain('tel:+81312345678')
        ->toContain('<strong>Watch out.</strong> Only pay our bank.')
        ->toContain('>STOCK</a>')
        ->not->toContain('HOW TO BUY');
});

it('shows the TOCO placeholder for a vehicle without a photo (TOC-VEH-007)', function () {
    expect(renderEmail(2))->toContain('/storage/email-assets/placeholder-vehicle.jpg');
});

it('escapes campaign text', function () {
    expect(renderEmail(2, ['headline' => '<b>Deals</b> & more']))
        ->toContain('&lt;b&gt;Deals&lt;/b&gt; &amp; more')
        ->not->toContain('<b>Deals</b>');
});

it('renders the same bytes twice for the same campaign (TOC-CB-003 basis)', function () {
    expect(renderEmail(4))->toBe(renderEmail(4));
});

it('adds UTM to site links but leaves other hosts, fragments and existing queries intact', function () {
    $tagger = new UtmTagger(['tocojapan.com']);
    $html = '<a href="https://tocojapan.com/vehicles?make=toyota#top" data-utm="E1">x</a>'
        .'<a href="https://wa.me/81900">w</a><a href="mailto:a@b.c">m</a><a href="https://www.tocojapan.com/">h</a>';

    $out = $tagger->tag($html, 'slug-1');

    expect($out)->toContain('href="https://tocojapan.com/vehicles?make=toyota&amp;utm_source=brevo&amp;utm_medium=email&amp;utm_campaign=slug-1&amp;utm_content=E1#top"')
        ->toContain('href="https://wa.me/81900"')
        ->toContain('href="mailto:a@b.c"')
        ->toContain('https://www.tocojapan.com/?utm_source=brevo&amp;utm_medium=email&amp;utm_campaign=slug-1&amp;utm_content=link');
});
