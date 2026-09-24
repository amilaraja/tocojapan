<?php

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Domain\Brevo\BrevoSendForbidden;
use Illuminate\Support\Facades\Http;
use Tests\Mailer\Support\MailerTest;

// Hard rule 1 (TOC-CMP-002): these requests must throw BEFORE any HTTP call.

beforeEach(function () {
    MailerTest::brevoKey();
    Http::fake(['*' => Http::response(['id' => 44], 201)]);
    $this->client = app(BrevoClient::class);
});

it('refuses to send, test-send, report or change status of a campaign', function (string $path) {
    expect(fn () => $this->client->post($path))->toThrow(BrevoSendForbidden::class);
    Http::assertNothingSent();
})->with([
    'emailCampaigns/12/sendNow',
    '/emailCampaigns/12/sendTest',
    'EMAILCAMPAIGNS/12/SENDNOW',
    'emailCampaigns/12/sendReport',
    'emailCampaigns/12/status',
    'smtp/email',
    'smtp/templates/3/sendTest',
    'smsCampaigns/1/sendNow',
    'transactionalSMS/sms',
    'whatsappCampaigns',
]);

it('refuses any payload with scheduledAt, at any depth', function (array $payload) {
    expect(fn () => $this->client->post('emailCampaigns', $payload))->toThrow(BrevoSendForbidden::class);
    expect(fn () => $this->client->put('emailCampaigns/5', $payload))->toThrow(BrevoSendForbidden::class);
    Http::assertNothingSent();
})->with([
    [['name' => 'x', 'scheduledAt' => '2026-10-01T10:00:00Z']],
    [['name' => 'x', 'recipients' => ['listIds' => [1], 'scheduledAt' => 'now']]],
]);

it('refuses to change a contact blacklist flag (TOC-BRV-002)', function () {
    expect(fn () => $this->client->post('contacts', ['email' => 'a@b.com', 'emailBlacklisted' => false]))->toThrow(BrevoSendForbidden::class);
    expect(fn () => $this->client->put('contacts/a@b.com', ['emailBlacklisted' => true]))->toThrow(BrevoSendForbidden::class);
    Http::assertNothingSent();
});

it('still allows creating and updating draft campaigns', function () {
    expect($this->client->post('emailCampaigns', ['name' => 'x', 'recipients' => ['listIds' => [1]]])->json('id'))->toBe(44);
    Http::assertSentCount(1);
});
