<?php

use App\Models\Make;
use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Domain\Webhooks\WebhookSender;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerWebhookDelivery;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\PartnerApi\Support\Partners;

/*
 * Change notifications (FR-SYNC-02, §8.5) and hold notices (FR-HLD-04).
 */

beforeEach(function () {
    config(['partner_api.webhooks.fake_resolve' => ['hooks.example' => '93.184.216.34', 'internal.example' => '10.0.0.7']]);
    $this->toyota = Make::create(['slug' => 'toyota', 'name' => 'Toyota', 'is_active' => true]);
    $this->nissan = Make::create(['slug' => 'nissan', 'name' => 'Nissan', 'is_active' => true]);
});

function hookPartner(array $attributes = []): Partner
{
    return Partners::make($attributes + ['webhook_url' => 'https://hooks.example/toco']);
}

function hookCar(array $attributes = []): Vehicle
{
    return Vehicle::factory()->create($attributes + ['status' => 'published', 'stock_no' => 'E0'.random_int(1000, 9999), 'price_fob' => 9000]);
}

it('queues a signed vehicle.sold notice and delivers it (FR-SYNC-02, §8.5)', function () {
    $partner = hookPartner();
    $car = hookCar();
    PartnerWebhookDelivery::query()->delete();

    $car->update(['status' => 'sold']);
    $delivery = PartnerWebhookDelivery::sole();
    expect($delivery->event)->toBe('vehicle.sold')->and($delivery->payload['data'])->toMatchArray(['stock_no' => $car->stock_no, 'status' => 'sold']);

    Http::fake(['hooks.example/*' => Http::response('', 204)]);
    expect(app(WebhookSender::class)->sendDue())->toBe(1);

    Http::assertSent(function (HttpRequest $request) use ($partner, $delivery) {
        preg_match('/t=(\d+),v1=([0-9a-f]+)/', $request->header('X-Toco-Signature')[0], $m);

        return $request->url() === 'https://hooks.example/toco'
            && $request->header('X-Toco-Event')[0] === 'vehicle.sold'
            && $request->header('X-Toco-Delivery')[0] === $delivery->delivery_id
            && hash_equals(hash_hmac('sha256', $m[1].'.'.$request->body(), $partner->fresh()->webhook_secret), $m[2]);
    });
    expect($delivery->fresh()->status)->toBe('delivered');
});

it('judges events from the partner\'s scope: in, out, and nothing for cars it never sees', function () {
    hookPartner(['stock_scope' => ['makes' => [$this->toyota->id]]]);
    $toyota = hookCar(['make_id' => $this->toyota->id]);
    $nissan = hookCar(['make_id' => $this->nissan->id]);
    expect(PartnerWebhookDelivery::pluck('event', 'subject')->all())->toBe([$toyota->stock_no => 'vehicle.created']);
    PartnerWebhookDelivery::query()->delete();

    $nissan->update(['title' => 'Nissan Caravan']);
    expect(PartnerWebhookDelivery::count())->toBe(0);

    $toyota->update(['make_id' => $this->nissan->id]);
    expect(PartnerWebhookDelivery::sole()->event)->toBe('vehicle.removed');
    PartnerWebhookDelivery::query()->delete();

    $nissan->update(['make_id' => $this->toyota->id]);
    expect(PartnerWebhookDelivery::sole()->event)->toBe('vehicle.created');
});

it('reports holds as status changes and deletions as removals', function () {
    hookPartner();
    $car = hookCar();
    PartnerWebhookDelivery::query()->delete();

    $hold = app(HoldService::class)->claim($car, new HoldClaim(VehicleHold::CHANNEL_ADMIN, 'JWT buyer'));
    expect(PartnerWebhookDelivery::sole()->payload['data']['status'])->toBe('reserved');
    PartnerWebhookDelivery::query()->update(['attempts' => 1]); // already sent: next change is a new delivery

    app(HoldService::class)->release($hold, 'test');
    $car->fresh()->delete();
    expect(PartnerWebhookDelivery::where('attempts', 0)->pluck('event')->all())->toBe(['vehicle.removed']);
});

it('coalesces unsent notices about the same car into the latest one', function () {
    hookPartner();
    $car = hookCar();
    $car->update(['price_fob' => 8000]);
    $car->update(['price_fob' => 7500]);

    expect(PartnerWebhookDelivery::count())->toBe(1);
});

it('ignores edits partners never see', function () {
    hookPartner();
    $car = hookCar();
    PartnerWebhookDelivery::query()->delete();

    $car->update(['fb_post_id' => 'x']);
    expect(PartnerWebhookDelivery::count())->toBe(0);
});

it('retries with backoff and gives up after 24 hours', function () {
    hookPartner();
    hookCar();
    Http::fake(['hooks.example/*' => Http::response('down', 503)]);

    app(WebhookSender::class)->sendDue();
    $delivery = PartnerWebhookDelivery::sole();
    expect($delivery->status)->toBe('pending')->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_status_code)->toBe(503)
        ->and($delivery->next_attempt_at->diffInSeconds(now(), true))->toBeGreaterThan(50);

    $this->travel(25)->hours();
    $delivery->forceFill(['next_attempt_at' => now()])->save();
    app(WebhookSender::class)->sendDue();
    expect($delivery->fresh()->status)->toBe('failed');
});

it('never calls private addresses or plain http (SSRF, §8.5)', function () {
    $partner = hookPartner(['webhook_url' => 'https://internal.example/hook']);
    Http::fake();

    [$ok, , $error] = app(WebhookSender::class)->ping($partner);
    expect($ok)->toBeFalse()->and($error)->toContain('public server');

    $partner->forceFill(['webhook_url' => 'http://hooks.example/hook'])->save();
    [$ok, , $error] = app(WebhookSender::class)->ping($partner);
    expect($ok)->toBeFalse()->and($error)->toContain('https');

    Http::assertNothingSent();
});

it('stops notifying suspended partners', function () {
    $partner = hookPartner();
    hookCar();
    $partner->suspend('Review');
    Http::fake();

    app(WebhookSender::class)->sendDue();
    expect(PartnerWebhookDelivery::sole()->status)->toBe('failed');
    Http::assertNothingSent();
});

it('warns a selling partner before its hold ends, and when it ends (FR-HLD-04)', function () {
    $partner = hookPartner(['type' => 'selling']);
    $car = hookCar();
    $hold = app(HoldService::class)->claim($car, new HoldClaim(channel: VehicleHold::CHANNEL_PARTNER, buyerRef: 'B1', partner: $partner, hours: 24));
    PartnerWebhookDelivery::query()->delete();
    Http::fake(['hooks.example/*' => Http::response('', 200)]);

    $this->travel(19)->hours();
    $this->artisan('partners:webhooks')->assertSuccessful();
    $this->artisan('partners:webhooks')->assertSuccessful();
    expect(PartnerWebhookDelivery::where('event', 'hold.expiring')->count())->toBe(1);

    $this->travel(6)->hours();
    app(HoldService::class)->expireDue();
    expect(PartnerWebhookDelivery::where('event', 'hold.expired')->sole()->payload['data']['hold_id'])->toBe($hold->public_id);
});

it('keeps the in-memory scope rules identical to the SQL ones', function () {
    $partner = Partners::make(['stock_scope' => ['makes' => [$this->toyota->id], 'year_min' => 2015, 'price_max' => 12000]]);
    $cars = collect([
        hookCar(['make_id' => $this->toyota->id, 'year_first_reg' => 2018, 'price_fob' => 10000]),
        hookCar(['make_id' => $this->toyota->id, 'year_first_reg' => 2012, 'price_fob' => 10000]),
        hookCar(['make_id' => $this->toyota->id, 'year_first_reg' => 2018, 'price_fob' => 15000, 'price_fob_discount' => 11000]),
        hookCar(['make_id' => $this->nissan->id, 'year_first_reg' => 2018, 'price_fob' => 10000]),
        hookCar(['make_id' => $this->toyota->id, 'year_first_reg' => 2016, 'price_fob' => 9000]),
        hookCar(['make_id' => $this->toyota->id, 'year_first_reg' => 2018, 'price_on_request' => true]),
        hookCar(['make_id' => $this->toyota->id, 'year_first_reg' => 2018, 'price_fob' => 9000, 'status' => 'draft']),
    ]);

    $sql = PartnerScope::visible($partner)->pluck('vehicles.id')->sort()->values()->all();
    $php = $cars->filter(fn (Vehicle $car) => PartnerScope::matches($partner, $car->fresh()))->pluck('id')->sort()->values()->all();

    expect($php)->toBe($sql)->and($sql)->toHaveCount(3);
});
