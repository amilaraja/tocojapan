<?php

use App\Modules\Crm\Domain\MailerHandshake;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Crm\Support\CrmData;
use Tests\Mailer\Support\MailerTest;

beforeEach(function () {
    CrmData::reset();
    Queue::fake([\App\Modules\Crm\Jobs\SendCrmEmail::class]);
});

it('keeps buyers at stage 02 to 05 on the Brevo "buying now" list, and only list membership changes', function () {
    MailerTest::brevoKey();
    $buyer = CrmData::buyer(['email' => 'paying@example.com']);
    CrmData::quote(CrmData::buyer(['email' => 'browsing@example.com']), CrmData::car());
    CrmData::order($buyer, CrmData::car());
    expect(CrmCase::where('stage', 2)->count())->toBe(1);

    Http::fake([
        '*/contacts/lists?*' => Http::response(['lists' => [], 'count' => 0]),
        '*/contacts/folders?*' => Http::response(['folders' => [['id' => 3]]]),
        '*/contacts/lists' => Http::response(['id' => 55], 201),
        '*/contacts/lists/55/contacts?*' => Http::response(['contacts' => [['email' => 'gone@example.com']]]),
        '*/contacts/lists/55/contacts/add' => Http::response(['contacts' => ['success' => ['paying@example.com']]], 201),
        '*/contacts/lists/55/contacts/remove' => Http::response(['contacts' => ['success' => ['gone@example.com']]], 201),
    ]);

    $result = MailerHandshake::sync(app(BrevoClient::class));

    expect($result)->toMatchArray(['list_id' => 55, 'added' => 1, 'removed' => 1])
        ->and(MailerHandshake::exclusionListId())->toBe(55);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/contacts/lists/55/contacts/add') && $r['emails'] === ['paying@example.com']);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/contacts/lists/55/contacts/remove') && $r['emails'] === ['gone@example.com']);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'emailCampaigns') || str_contains(strtolower($r->body()), 'blacklist'));
});
