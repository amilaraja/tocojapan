<?php

use App\Modules\Mailer\Domain\Importer\AddressExtractor;
use App\Modules\Mailer\Domain\Importer\Extraction;
use App\Modules\Mailer\Domain\Importer\FieldRuleEngine;
use App\Modules\Mailer\Domain\Importer\MessageParser;
use App\Modules\Mailer\Domain\Importer\ParsedMessage;
use App\Modules\Mailer\Domain\Importer\SenderMatcher;
use App\Modules\Mailer\Models\IgnoreRule;
use App\Modules\Mailer\Support\MailerSettings;
use Tests\Mailer\Support\FakeMailbox;
use Tests\Mailer\Support\MailerTest;

beforeEach(function () {
    MailerTest::fakeDns();
    app(MailerSettings::class)->set('own_domains', ['tocojapan.com']);
});

function extractFixture(string $fixture, $sender): array
{
    return app(Extraction::class)->run(MessageParser::fromRaw(FakeMailbox::fixture($fixture), $fixture), $sender);
}

function kept(array $result): array
{
    return array_values(array_map(fn ($a) => $a['email'], array_filter($result['addresses'], fn ($a) => $a['keep'])));
}

function reasons(array $result): array
{
    return collect($result['addresses'])->mapWithKeys(fn ($a) => [$a['email'] => $a['reason']])->all();
}

// ---- One fixture set per approved sender (TOC-NFR-004) ----

it('extracts the buyer, name, phone and stock ref from a website inquiry (sender: tocojapan-inquiry 01)', function () {
    $r = extractFixture('tocojapan-inquiry/01-basic.eml', MailerTest::tocoSender());

    expect(kept($r))->toBe(['adam.steiger@gmail.com'])
        ->and($r['fields'])->toBe(['FIRSTNAME' => 'Adam', 'LASTNAME' => 'Steiger', 'PHONE' => '+256 772 123456', 'STOCK_REF' => 'E02056']);
});

it('reads a base64 inquiry without a phone line (sender: tocojapan-inquiry 02)', function () {
    $r = extractFixture('tocojapan-inquiry/02-no-phone.eml', MailerTest::tocoSender());

    expect(kept($r))->toBe(['maria.kalumba@yahoo.co.uk'])
        ->and($r['fields'])->toBe(['FIRSTNAME' => 'Maria', 'LASTNAME' => 'Kalumba']);
});

it('finds an address that only appears in an HTML mailto link (TOC-EXT-002, sender: example-portal 01)', function () {
    $sender = MailerTest::portalSender();
    $r = extractFixture('example-portal/01-mailto-only.eml', $sender);

    expect(kept($r))->toBe(['jose.pereira@example.org'])
        ->and(reasons($r)['noreply@example-portal.com'])->toBe('system_address')
        ->and($r['fields'])->toBe(['FIRSTNAME' => 'José', 'LASTNAME' => 'Pereira', 'COUNTRY' => 'Mozambique']);
});

it('adds the Reply-To address only when enabled for the sender', function () {
    $r = extractFixture('example-portal/01-mailto-only.eml', MailerTest::portalSender(['use_reply_to' => true]));

    expect(kept($r))->toBe(['jose.pereira@example.org', 'buyer.reply@outlook.com']);
});

it('excludes TOCO, system, sender and ignored addresses (TOC-EXT-004, TOC-EXT-009, sender: example-portal 02)', function () {
    IgnoreRule::create(['value' => '@competitor.jp', 'type' => 'domain']);

    $r = extractFixture('example-portal/02-exclusions.eml', MailerTest::portalSender());

    expect(kept($r))->toBe(['buyer@gmail.com'])
        ->and(reasons($r))->toMatchArray([
            'info@tocojapan.com' => 'own_domain',
            'noreply@portal.com' => 'system_address',
            'leads@example-portal.com' => 'sender_address',
            'sales@competitor.jp' => 'ignored',
        ]);
});

it('keeps the per-message limit and logs the rest as over_limit (TOC-EXT-007, sender: example-portal 03)', function () {
    $r = extractFixture('example-portal/03-five-buyers.eml', MailerTest::portalSender(['max_per_message' => 3]));

    expect(kept($r))->toBe(['one@buyers.com', 'two@buyers.com', 'three@buyers.com'])
        ->and(reasons($r)['four@buyers.com'])->toBe('over_limit')
        ->and(reasons($r)['five@buyers.com'])->toBe('over_limit');
});

it('normalises addresses (TOC-EXT-003)', function () {
    expect(AddressExtractor::normalise('  <John.Doe@Example.COM>, '))->toBe('john.doe@example.com');
});

it('skips a domain with no mail server as no_mail_domain (TOC-EXT-005)', function () {
    $message = new ParsedMessage('m1', 'leads@example-portal.com', null, null, null, 'Buyer: buyer@nonexistent-domain-xyz.invalid', '');

    $r = app(Extraction::class)->run($message, MailerTest::portalSender());

    expect(reasons($r))->toBe(['buyer@nonexistent-domain-xyz.invalid' => 'no_mail_domain']);
});

it('splits "Name: (.+)" into first and last name (TOC-EXT-006)', function () {
    expect((new FieldRuleEngine)->apply([['field' => 'name', 'pattern' => 'Name: (.+)']], "Name: Adam Steiger\nCountry: UK"))
        ->toBe(['FIRSTNAME' => 'Adam', 'LASTNAME' => 'Steiger']);
});

it('rejects field rule patterns that are broken or have no capture group', function (string $pattern, bool $ok) {
    expect(FieldRuleEngine::isValid($pattern))->toBe($ok);
})->with([
    ['Name: (.+)', true],
    ['Name: .+', false],
    ['Name: (.+', false],
    ['', false],
]);

it('matches senders by exact address or @domain (TOC-EXT-001)', function () {
    $senders = collect([MailerTest::portalSender(), MailerTest::tocoSender()]);
    $m = new SenderMatcher;

    expect($m->match('leads@example-portal.com', $senders)?->label)->toBe('Example portal')
        ->and($m->match('info@tocojapan.com', $senders)?->label)->toBe('Website inquiry form')
        ->and($m->match('other@tocojapan.com', $senders))->toBeNull()
        ->and($m->match('x@evil-example-portal.com', $senders))->toBeNull()
        ->and($m->query($senders))->toBe('from:(@example-portal.com OR info@tocojapan.com)');
});

it('decodes quoted-printable and non-UTF-8 parts', function () {
    $msg = MessageParser::fromRaw(FakeMailbox::fixture('tocojapan-inquiry/01-basic.eml'));

    expect($msg->from)->toBe('info@tocojapan.com')
        ->and($msg->html)->toContain('+256 772 123456')
        ->and($msg->receivedAt?->toIso8601String())->toBe('2026-09-22T01:15:00+00:00');

    expect(MessageParser::fromRaw(FakeMailbox::fixture('example-portal/01-mailto-only.eml'))->html)->toContain('José');
});
