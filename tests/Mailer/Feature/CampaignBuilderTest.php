<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder;
use App\Modules\Mailer\Domain\Campaigns\CampaignRenderer;
use App\Modules\Mailer\Domain\Campaigns\StatsSync;
use App\Modules\Mailer\Domain\Vehicles\VehicleSource;
use App\Modules\Mailer\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Modules\Mailer\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Modules\Mailer\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Modules\Mailer\Models\Banner;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Support\MailerAccess;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Mailer\Support\MailerTest;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Storage::fake('public');
    $this->seed(MailerPermissionSeeder::class);
    MailerTest::brevoKey();
    Cache::put('mailer:brevo:lists', [7 => 'Buyers (10)'], 600);
    Cache::put('mailer:brevo:senders', [3 => 'TOCO <sales@tocojapan.com>'], 600);
    $this->marketer = User::factory()->create();
    $this->marketer->assignRole(MailerAccess::ROLE_MARKETER);
    $this->actingAs($this->marketer);
});

function stock(int $n, array $attrs = []): array
{
    return collect(range(1, $n))->map(fn ($i) => Vehicle::factory()->create(array_merge(['stock_no' => sprintf('E%05d', 3000 + $i), 'price_fob' => 1000 * $i, 'price_fob_discount' => null], $attrs)))->all();
}

function builderCampaign(array $vehicles, array $over = []): Campaign
{
    $campaign = Campaign::create(array_merge([
        'name' => 'Week 39', 'subject' => 'Fresh stock', 'preview_text' => 'Hot deals', 'kicker' => 'PICKS',
        'headline' => 'Ready to ship', 'intro' => 'Inspected in Japan.', 'sender_id' => 3, 'list_ids' => [7],
        'status' => Campaign::STATUS_DRAFT,
    ], $over));

    $source = app(VehicleSource::class);
    foreach (array_values($vehicles) as $i => $v) {
        $campaign->vehicles()->create(['vehicle_id' => $v->id, 'stock_ref' => $v->stock_no, 'position' => $i, 'snapshot' => $source->find($v->id)->toArray(), 'added_at' => now()]);
    }

    return $campaign;
}

function fakeBrevoCampaigns(string $status = 'draft'): void
{
    Http::fake([
        'api.brevo.com/v3/emailCampaigns' => Http::response(['id' => 501], 201),
        'api.brevo.com/v3/emailCampaigns/*' => fn (Request $r) => $r->method() === 'GET'
            ? Http::response(['id' => 501, 'status' => $status])
            : Http::response(null, 204),
    ]);
}

it('lists campaigns for Marketers, newest first, filterable by status (TOC-CB-008)', function () {
    $old = Campaign::create(['name' => 'Old one', 'status' => 'sent']);
    $old->forceFill(['created_at' => now()->subDay()])->save();
    Campaign::create(['name' => 'New one', 'status' => 'draft']);

    $this->get('/admin/mailer/campaigns')->assertOk()->assertSeeInOrder(['New one', 'Old one']);

    Livewire::test(ListCampaigns::class)
        ->filterTable('status', 'sent')
        ->assertCanSeeTableRecords([$old])
        ->assertCountTableRecords(1);
});

it('shows a friendly empty state', function () {
    $this->get('/admin/mailer/campaigns')->assertOk()->assertSee('Create your first campaign');
});

it('rejects a 121-character subject (TOC-CB-001)', function () {
    Livewire::test(CreateCampaign::class)
        ->fillForm(['name' => 'x', 'subject' => str_repeat('a', 121)])
        ->call('create')
        ->assertHasFormErrors(['subject' => 'max']);
});

it('creates a campaign and opens the builder', function () {
    Livewire::test(CreateCampaign::class)
        ->fillForm(['name' => 'Week 40', 'subject' => 'Hello'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $c = Campaign::sole();
    expect($c->status)->toBe('draft')
        ->and($c->created_by)->toBe($this->marketer->id)
        ->and($c->slug)->toStartWith('week-40-');
});

it('adds available vehicles only, never sold ones', function () {
    [$a, $b] = stock(2);
    [$sold] = stock(1, ['stock_no' => 'E09999', 'status' => 'sold', 'sold_at' => now()]);
    $campaign = builderCampaign([]);

    Livewire::test(EditCampaign::class, ['record' => $campaign->id])
        ->callAction('addVehicles', ['vehicle_ids' => [$a->id, $b->id, $sold->id]])
        ->assertHasNoActionErrors();

    expect($campaign->vehicles()->pluck('vehicle_id')->all())->toBe([$a->id, $b->id]);
});

it('blocks a 13th vehicle and disables push with one vehicle (TOC-CB-002)', function () {
    $vehicles = stock(13);
    $twelve = builderCampaign(array_slice($vehicles, 0, 12));

    Livewire::test(EditCampaign::class, ['record' => $twelve->id])
        ->assertActionDisabled('addVehicles');
    expect($twelve->vehicles()->count())->toBe(12);

    $one = builderCampaign(array_slice($vehicles, 0, 1), ['name' => 'One']);
    Livewire::test(EditCampaign::class, ['record' => $one->id])
        ->assertActionDisabled('push')
        ->assertSee('add at least 2 vehicles');
});

it('saves the vehicle order and removals, with keyboard move buttons available (TOC-CB-002, TOC-NFR-006)', function () {
    [$a, $b, $c] = stock(3);
    $campaign = builderCampaign([$a, $b, $c]);

    $page = Livewire::test(EditCampaign::class, ['record' => $campaign->id])->assertSee('Move up');
    $keys = array_keys($page->get('data.vehicles'));
    $page->set('data.vehicles', [$keys[2] => ['id' => $c->id], $keys[0] => ['id' => $a->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($campaign->vehicles()->pluck('vehicle_id')->all())->toBe([$c->id, $a->id]);
});

it('shows a price changed notice with the new price (TOC-CB-004)', function () {
    [$a, $b] = stock(2);
    $campaign = builderCampaign([$a, $b]);
    $a->forceFill(['price_fob' => 2750])->saveQuietly();
    $campaign->vehicles()->where('vehicle_id', $a->id)->first()->forceFill(['snapshot' => [...app(VehicleSource::class)->find($a->id)->toArray(), 'priceFob' => 2750.0]])->save();
    $a->forceFill(['price_fob' => 2650])->saveQuietly();

    Livewire::test(EditCampaign::class, ['record' => $campaign->id])
        ->assertSee('Price changed from $2,750 to $2,650. The email will show the new price.');

    expect((float) $campaign->vehicles()->where('vehicle_id', $a->id)->first()->snapshot['priceFob'])->toBe(2650.0);
});

it('blocks push for a sold vehicle with no Brevo request (TOC-CB-005, UAT-5)', function () {
    Http::fake();
    [$a, $b] = stock(2);
    $campaign = builderCampaign([$a, $b]);
    $a->forceFill(['status' => 'sold', 'sold_at' => now()])->saveQuietly();

    Livewire::test(EditCampaign::class, ['record' => $campaign->id])
        ->assertSee($a->stock_no.' was sold. Remove it before pushing.')
        ->callAction('push')
        ->assertNotified('Not pushed');

    Http::assertNothingSent();
    expect($campaign->fresh()->brevo_campaign_id)->toBeNull();
});

it('pushes a draft with no schedule, and preview HTML equals pushed HTML (TOC-CMP-001, TOC-CB-003, UAT-4)', function () {
    fakeBrevoCampaigns();
    $campaign = builderCampaign(stock(6));
    $preview = app(CampaignRenderer::class)->render($campaign->fresh());

    Livewire::test(EditCampaign::class, ['record' => $campaign->id])
        ->callAction('push')
        ->assertNotified('Draft created in Brevo. Nothing has been sent yet.');

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://api.brevo.com/v3/emailCampaigns'
        && $r['subject'] === 'Fresh stock' && $r['previewText'] === 'Hot deals' && $r['sender'] === ['id' => 3]
        && $r['recipients'] === ['listIds' => [7]] && ! isset($r['scheduledAt'])
        && $r['htmlContent'] === $preview);

    $fresh = $campaign->fresh();
    expect($fresh->brevo_campaign_id)->toBe(501)
        ->and($fresh->status)->toBe('in_brevo')
        ->and($fresh->pushed_html_hash)->toBe(hash('sha256', $preview));

    $this->get("/admin/mailer/campaigns/{$campaign->id}/edit")->assertSee('Open in Brevo')->assertSee('Brevo campaign #501');
});

it('updates the same Brevo draft on re-push (TOC-CMP-003)', function () {
    fakeBrevoCampaigns('draft');
    $campaign = builderCampaign(stock(2), ['brevo_campaign_id' => 501, 'status' => 'in_brevo']);

    Livewire::test(EditCampaign::class, ['record' => $campaign->id])->callAction('push');

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/emailCampaigns/501'));
    Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    expect($campaign->fresh()->brevo_campaign_id)->toBe(501);
});

it('refuses to push over a campaign Brevo has already sent', function () {
    fakeBrevoCampaigns('sent');
    $campaign = builderCampaign(stock(2), ['brevo_campaign_id' => 501, 'status' => 'in_brevo']);

    Livewire::test(EditCampaign::class, ['record' => $campaign->id])
        ->callAction('push')
        ->assertNotified('Not pushed');

    Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PUT', 'POST'], true));
});

it('fails a push within the time budget when Brevo is down, with no campaign saved (TOC-NFR-005)', function () {
    Http::fake(['*' => Http::response([], 503)]);
    $campaign = builderCampaign(stock(2));

    $started = now();
    Livewire::test(EditCampaign::class, ['record' => $campaign->id])->callAction('push')->assertNotified('Not pushed');

    expect($started->diffInSeconds(now()))->toBeLessThan(25)
        ->and($campaign->fresh()->brevo_campaign_id)->toBeNull();
});

it('marks a pushed campaign as Changed since push after an edit (TOC-CB-006)', function () {
    fakeBrevoCampaigns();
    $campaign = builderCampaign(stock(2));
    Livewire::test(EditCampaign::class, ['record' => $campaign->id])->callAction('push');

    Livewire::test(EditCampaign::class, ['record' => $campaign->id])
        ->fillForm(['headline' => 'A new headline'])
        ->call('save');

    expect($campaign->fresh()->status)->toBe('changed');
});

it('duplicates a sent campaign as a new draft with the same vehicles and banner (TOC-CB-007, UAT-7)', function () {
    $banner = Banner::create(['name' => 'B', 'path' => 'email-assets/banners/b.jpg', 'width' => 1200, 'height' => 440, 'bytes' => 1, 'alt_text' => 'b']);
    $campaign = builderCampaign(stock(3), ['banner_id' => $banner->id, 'brevo_campaign_id' => 501, 'status' => 'sent', 'stats' => ['opens' => 9]]);

    Livewire::test(ListCampaigns::class)->callTableAction('duplicate', $campaign);

    $copy = Campaign::latest('id')->first();
    expect($copy->id)->not->toBe($campaign->id)
        ->and($copy->status)->toBe('draft')
        ->and($copy->brevo_campaign_id)->toBeNull()
        ->and($copy->stats)->toBeNull()
        ->and($copy->banner_id)->toBe($banner->id)
        ->and($copy->vehicles()->pluck('vehicle_id')->all())->toBe($campaign->vehicles()->pluck('vehicle_id')->all())
        ->and($copy->slug)->not->toBe($campaign->slug);
});

it('syncs status and statistics from Brevo (TOC-CMP-006, UAT-6)', function () {
    $campaign = builderCampaign(stock(2), ['brevo_campaign_id' => 501, 'status' => 'in_brevo', 'pushed_at' => now()->subDay()]);
    Http::fake(['api.brevo.com/v3/emailCampaigns/501*' => Http::response([
        'id' => 501, 'status' => 'sent', 'sentDate' => '2026-09-24T09:00:00Z',
        'statistics' => ['globalStats' => ['sent' => 120, 'delivered' => 118, 'uniqueViews' => 40, 'uniqueClicks' => 12, 'unsubscriptions' => 1]],
    ])]);

    expect(app(StatsSync::class)->run())->toBe(1);

    $fresh = $campaign->fresh();
    expect($fresh->status)->toBe('sent')
        ->and($fresh->sent_at->toIso8601String())->toBe('2026-09-24T09:00:00+00:00')
        ->and($fresh->stats)->toMatchArray(['recipients' => 120, 'opens' => 40, 'clicks' => 12, 'unsubscribes' => 1]);
});

it('does not sync campaigns pushed more than 60 days ago', function () {
    Http::fake();
    builderCampaign(stock(2), ['brevo_campaign_id' => 501, 'status' => 'in_brevo', 'pushed_at' => now()->subDays(61)]);

    expect(app(StatsSync::class)->run())->toBe(0);
    Http::assertNothingSent();
});

it('shows the preview modal with the real email', function () {
    $campaign = builderCampaign(stock(2));

    Livewire::test(EditCampaign::class, ['record' => $campaign->id])
        ->callAction('preview')
        ->assertActionMounted('showPreview')
        ->assertMountedActionModalSee('Desktop (600px)')
        ->assertMountedActionModalSee('Mobile (375px)');
});
