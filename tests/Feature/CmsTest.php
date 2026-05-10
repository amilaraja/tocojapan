<?php

use App\Models\Country;
use App\Models\Make;
use App\Models\Page;
use App\Models\Port;
use App\Models\Vehicle;
use App\Models\VehicleModel;

it('renders a published default page', function () {
    Page::create([
        'template_key' => 'default',
        'slug' => 'about',
        'title' => 'About Toco',
        'data' => ['headline' => 'Hello world', 'kicker' => 'Since 2009', 'body' => '<p>Body copy.</p>'],
        'status' => 'published',
        'locale' => 'en',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/about')
        ->assertOk()
        ->assertSee('Hello world')
        ->assertSee('Since 2009')
        ->assertSee('Body copy.', escape: false);
});

it('returns 404 for a draft page', function () {
    Page::create([
        'template_key' => 'default',
        'slug' => 'hidden',
        'title' => 'Hidden',
        'data' => ['headline' => 'Hidden headline'],
        'status' => 'draft',
        'locale' => 'en',
    ]);

    $this->get('/hidden')->assertNotFound();
});

it('returns 404 for a published page whose published_at is in the future', function () {
    Page::create([
        'template_key' => 'default',
        'slug' => 'future',
        'title' => 'Future',
        'data' => ['headline' => 'Future headline'],
        'status' => 'published',
        'locale' => 'en',
        'published_at' => now()->addDay(),
    ]);

    $this->get('/future')->assertNotFound();
});

it('renders a country-landing page with the country ports and popular vehicles', function () {
    $country = Country::create(['iso2' => 'LK', 'name' => 'Sri Lanka', 'slug' => 'sri-lanka', 'is_active' => true]);
    Port::create(['country_id' => $country->id, 'name' => 'Colombo', 'slug' => 'colombo', 'unlocode' => 'LKCMB', 'rate_per_m3' => 25, 'is_active' => true]);

    $make = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'aqua', 'name' => 'Aqua']);
    Vehicle::factory()->create([
        'make_id' => $make->id,
        'vehicle_model_id' => $model->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'title' => 'Toyota Aqua Sample',
    ]);

    Page::create([
        'template_key' => 'country-landing',
        'slug' => 'sri-lanka',
        'title' => 'Japanese cars to Sri Lanka',
        'data' => ['country_id' => $country->id, 'headline' => 'Japanese cars to Sri Lanka'],
        'status' => 'published',
        'locale' => 'en',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/sri-lanka')->assertOk();
    $response->assertSee('Japanese cars to Sri Lanka');
    $response->assertSee('Colombo');
    $response->assertSee('LKCMB');
    $response->assertSee('Toyota Aqua Sample');
});

it('renders SEO meta tags from the page', function () {
    Page::create([
        'template_key' => 'default',
        'slug' => 'seo-test',
        'title' => 'SEO Test',
        'data' => ['headline' => 'SEO headline'],
        'status' => 'published',
        'locale' => 'en',
        'published_at' => now()->subDay(),
        'seo_title' => 'Custom SEO title',
        'seo_description' => 'Custom SEO description for crawlers',
    ]);

    $response = $this->get('/seo-test')->assertOk();
    $response->assertSee('Custom SEO title');
    $response->assertSee('Custom SEO description for crawlers', escape: false);
    $response->assertSee('og:title', escape: false);
    $response->assertSee('canonical', escape: false);
});

it('serves a sitemap.xml that includes published page slugs', function () {
    Page::create([
        'template_key' => 'default',
        'slug' => 'about',
        'title' => 'About',
        'data' => ['headline' => 'About'],
        'status' => 'published',
        'locale' => 'en',
        'published_at' => now()->subDay(),
    ]);
    Page::create([
        'template_key' => 'default',
        'slug' => 'draft-page',
        'title' => 'Draft',
        'data' => ['headline' => 'Draft'],
        'status' => 'draft',
        'locale' => 'en',
    ]);

    $response = $this->get('/sitemap.xml')->assertOk();
    $body = $response->getContent();

    expect($body)->toContain('<urlset')
        ->and($body)->toContain('/about')
        ->and($body)->not->toContain('/draft-page');
});

it('serves robots.txt that disallows admin routes and points to the sitemap', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: ');
});
