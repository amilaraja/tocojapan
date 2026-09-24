<?php

use App\Modules\Mailer\Domain\Brevo\ContactSync;
use App\Modules\Mailer\Models\ApprovedSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Mailer\Support\MailerTest;

beforeEach(function () {
    MailerTest::brevoKey();
    $this->sender = MailerTest::tocoSender();
    $this->travelTo(now()->setTimezone('Asia/Tokyo')->setDate(2026, 9, 24)->setTime(10, 0));
});

function brevoContact(array $over = []): array
{
    return array_replace_recursive(['email' => 'adam@gmail.com', 'emailBlacklisted' => false, 'listIds' => [], 'attributes' => [], 'statistics' => []], $over);
}

it('creates a new contact on the sender lists with SOURCE and both dates (TOC-BRV-001, 004)', function () {
    Http::fake([
        'api.brevo.com/v3/contacts/adam%40gmail.com' => Http::response(['message' => 'not found'], 404),
        'api.brevo.com/v3/contacts' => Http::response(['id' => 99], 201),
    ]);

    $r = app(ContactSync::class)->sync('adam@gmail.com', ['FIRSTNAME' => 'Adam', 'PHONE' => '+256 1'], $this->sender);

    expect($r->outcome)->toBe('added')->and($r->status)->toBe(201);
    Http::assertSent(fn (Request $req) => $req->method() === 'POST' && $req->url() === 'https://api.brevo.com/v3/contacts'
        && $req['updateEnabled'] === true && $req['listIds'] === [7]
        && $req['attributes'] == ['SOURCE' => 'Website inquiry form', 'TOCO_LAST_ENQUIRY_AT' => '2026-09-24', 'TOCO_IMPORTED_AT' => '2026-09-24', 'FIRSTNAME' => 'Adam', 'PHONE' => '+256 1']);
});

it('updates an existing contact without overwriting names or TOCO_IMPORTED_AT (TOC-BRV-004, UAT-2)', function () {
    Http::fake([
        'api.brevo.com/v3/contacts/adam%40gmail.com' => Http::response(brevoContact(['attributes' => ['FIRSTNAME' => 'Adam', 'TOCO_IMPORTED_AT' => '2026-09-01']])),
        'api.brevo.com/v3/contacts' => Http::response(null, 204),
    ]);

    $r = app(ContactSync::class)->sync('adam@gmail.com', ['FIRSTNAME' => 'Adrian', 'LASTNAME' => 'Steiger'], $this->sender);

    expect($r->outcome)->toBe('updated');
    Http::assertSent(fn (Request $req) => $req->method() === 'POST'
        && $req['attributes'] == ['SOURCE' => 'Website inquiry form', 'TOCO_LAST_ENQUIRY_AT' => '2026-09-24', 'LASTNAME' => 'Steiger']);
});

it('skips blacklisted contacts as unsubscribed and never writes to them (TOC-BRV-002, UAT-3)', function () {
    Http::fake(['api.brevo.com/v3/contacts/*' => Http::response(brevoContact(['emailBlacklisted' => true]))]);

    $r = app(ContactSync::class)->sync('adam@gmail.com', [], $this->sender);

    expect($r->outcome)->toBe('skipped')->and($r->reason)->toBe('unsubscribed');
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $req) => $req->method() !== 'GET');
});

it('skips hard-bounced contacts as bounced (TOC-BRV-003)', function () {
    Http::fake(['api.brevo.com/v3/contacts/*' => Http::response(brevoContact(['statistics' => ['hardBounces' => [['campaignId' => 3]]]]))]);

    $r = app(ContactSync::class)->sync('adam@gmail.com', [], $this->sender);

    expect($r->outcome)->toBe('skipped')->and($r->reason)->toBe('bounced');
    Http::assertSentCount(1);
});

it('uses double opt-in for "Confirm first" senders (TOC-BRV-006)', function () {
    $sender = MailerTest::portalSender(['consent_mode' => ApprovedSender::CONSENT_CONFIRM, 'doi_template_id' => 12, 'doi_redirect_url' => 'https://tocojapan.com/thanks']);
    Http::fake([
        'api.brevo.com/v3/contacts/new%40gmail.com' => Http::response([], 404),
        'api.brevo.com/v3/contacts/doubleOptinConfirmation' => Http::response(null, 201),
    ]);

    $r = app(ContactSync::class)->sync('new@gmail.com', [], $sender);

    expect($r->outcome)->toBe('added')->and($r->reason)->toBe('confirmation_sent');
    Http::assertSent(fn (Request $req) => str_ends_with($req->url(), 'doubleOptinConfirmation')
        && $req['includeListIds'] === [8] && $req['templateId'] === 12 && $req['redirectionUrl'] === 'https://tocojapan.com/thanks');
    Http::assertNotSent(fn (Request $req) => $req->url() === 'https://api.brevo.com/v3/contacts');
});

it('only updates attributes for a "Confirm first" contact already on the lists', function () {
    $sender = MailerTest::portalSender(['consent_mode' => ApprovedSender::CONSENT_CONFIRM, 'doi_template_id' => 12, 'doi_redirect_url' => 'https://x.test']);
    Http::fake([
        'api.brevo.com/v3/contacts/old%40gmail.com' => Http::response(brevoContact(['email' => 'old@gmail.com', 'listIds' => [8]])),
        'api.brevo.com/v3/contacts' => Http::response(null, 204),
    ]);

    $r = app(ContactSync::class)->sync('old@gmail.com', [], $sender);

    expect($r->outcome)->toBe('updated');
    Http::assertSent(fn (Request $req) => $req->url() === 'https://api.brevo.com/v3/contacts' && ! isset($req['listIds']));
    Http::assertNotSent(fn (Request $req) => str_ends_with($req->url(), 'doubleOptinConfirmation'));
});

it('marks other Brevo errors as failed', function () {
    Http::fake([
        'api.brevo.com/v3/contacts/*' => Http::response([], 404),
        'api.brevo.com/v3/contacts' => Http::response(['code' => 'invalid_parameter'], 400),
    ]);

    $r = app(ContactSync::class)->sync('adam@gmail.com', [], $this->sender);

    expect($r->outcome)->toBe('failed')->and($r->reason)->toBe('brevo_error')->and($r->status)->toBe(400);
});
