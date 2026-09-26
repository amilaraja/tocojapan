<?php

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Domain\Brevo\BrevoNotConfigured;
use App\Modules\Mailer\Domain\Brevo\BrevoUnavailable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Mailer\Support\MailerTest;

beforeEach(fn () => MailerTest::brevoKey('xkeysib-abc-1234'));

it('sends the key header to the v3 base URL', function () {
    Http::fake(['api.brevo.com/v3/account' => Http::response(['email' => 'a@b.c'])]);

    app(BrevoClient::class)->get('account');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.brevo.com/v3/account' && $r->header('api-key') === ['xkeysib-abc-1234']);
});

it('retries 429 twice then succeeds, recording 2 retries (TOC-BRV-005)', function () {
    Http::fake(['*' => Http::sequence()->push([], 429)->push([], 429)->push(['id' => 1], 201)]);

    $client = app(BrevoClient::class);
    $response = $client->post('contacts', ['email' => 'a@b.com']);

    expect($response->status())->toBe(201)->and($client->lastRetries)->toBe(2);
    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('honours Retry-After', function () {
    Http::fake(['*' => Http::sequence()->push([], 429, ['Retry-After' => '7'])->push([], 200)]);

    app(BrevoClient::class)->get('account');

    Sleep::assertSequence([Sleep::for(7)->seconds()]);
});

it('gives up after 5 retries with a readable error', function () {
    Http::fake(['*' => Http::response([], 503)]);

    expect(fn () => app(BrevoClient::class)->get('account'))
        ->toThrow(BrevoUnavailable::class, 'Brevo is busy or having problems (code 503)');
    Http::assertSentCount(6);
});

it('does not retry client errors', function () {
    Http::fake(['*' => Http::response(['message' => 'bad'], 400)]);

    expect(app(BrevoClient::class)->post('contacts', ['email' => 'x'])->status())->toBe(400);
    Http::assertSentCount(1);
});

it('stops retrying when the time budget would run out', function () {
    Http::fake(['*' => Http::response([], 500)]);

    expect(fn () => app(BrevoClient::class)->withBudget(3)->get('account'))->toThrow(BrevoUnavailable::class);
    Http::assertSentCount(2); // tries, waits 1 s, tries, next wait (2 s) would pass 3 s
});

it('explains when Brevo is not connected', function () {
    app(\App\Modules\Mailer\Support\MailerSettings::class)->set('brevo_api_key', null);
    config(['mailer.brevo.api_key' => null]);
    Http::fake();

    expect(fn () => app(BrevoClient::class)->get('account'))->toThrow(BrevoNotConfigured::class);
    Http::assertNothingSent();
});

it('explains an IP block from Brevo in plain words, without retrying', function () {
    Http::fake(['*' => Http::response(['code' => 'unauthorized', 'message' => 'We have detected you are using an unrecognised IP address 172.104.62.81. If you performed this action make sure to add the new IP address in this link: https://app.brevo.com/security/authorised_ips'], 401)]);

    expect(fn () => app(BrevoClient::class)->get('account'))
        ->toThrow(\App\Modules\Mailer\Domain\Brevo\BrevoRejected::class, "Brevo blocked this server's address (172.104.62.81). In Brevo, open Security, Authorised IPs and add 172.104.62.81");
    Http::assertSentCount(1);
});

it('says the key was not accepted for other 401s', function () {
    Http::fake(['*' => Http::response(['code' => 'unauthorized', 'message' => 'Key not found'], 401)]);

    expect(fn () => app(BrevoClient::class)->get('account'))
        ->toThrow(\App\Modules\Mailer\Domain\Brevo\BrevoRejected::class, 'Brevo did not accept the key.');
});
