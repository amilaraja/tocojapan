<?php

use App\Models\User;
use App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder;
use App\Modules\Mailer\Domain\Buyers\BuyerBackfill;
use App\Modules\Mailer\Domain\Buyers\BuyerDetailsParser;
use App\Modules\Mailer\Domain\Buyers\BuyerExport;
use App\Modules\Mailer\Domain\Buyers\BuyerNormalizer;
use App\Modules\Mailer\Domain\Buyers\BuyerRecorder;
use App\Modules\Mailer\Domain\Importer\ImportRunner;
use App\Modules\Mailer\Domain\Importer\ParsedMessage;
use App\Modules\Mailer\Filament\Pages\Buyers\MatchingPage;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\Buyer;
use App\Modules\Mailer\Models\BuyerEnquiry;
use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Models\ProcessedMessage;
use App\Modules\Mailer\Support\MailerAccess;
use App\Modules\Mailer\Support\MailerSettings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Mailer\Support\MailerTest;

// Synthetic Japanese Car Trade enquiry (made-up buyer; same layout as the real emails).
function jctText(array $o = []): string
{
    $v = array_merge([
        'buyer' => 'Mr. Jim Example', 'country' => 'Kenya', 'port' => 'Mombasa', 'email' => 'jim.example@gmail.com',
        'phone' => '0712 345 678', 'iam' => 'Individual', 'make' => 'Toyota', 'model' => 'Hiace Van', 'year' => '2016', 'drive' => 'RHD',
        'message' => "Hi\nPlease send CIF price.\nBudget : 999999\nName : Somebody Else",
        'extra' => '',
    ], $o);

    return <<<TXT
Dear Mr. Staff,
TOCO INTERNATIONAL Co., LTD

{$v['buyer']} from {$v['country']} posted below inquiry at www.japanesecartrade.com on 8th October, 2026.

Make : {$v['make']}
Model Name : {$v['model']}
Mfg. Year : {$v['year']}
Drive : {$v['drive']}
{$v['extra']}
Message :
{$v['message']}

Sender Information :
------------------------------------------------------
Name : {$v['buyer']}
Country : {$v['country']}
Port of Destination : {$v['port']}
E-mail : {$v['email']}
Phone/Mobile : {$v['phone']}
I am : {$v['iam']}

Thanks & Regards.
The Support Team
TXT;
}

function jctMessage(string $id, array $o = [], ?CarbonImmutable $at = null, string $subject = 'Inquiry For Toyota Hiace Van from Kenya [www.japanesecartrade.com]'): ParsedMessage
{
    return new ParsedMessage($id, 'inquiry@japanesecartrade.com', $o['email'] ?? 'jim.example@gmail.com', $subject, $at ?? CarbonImmutable::now()->subHour(), jctText($o), '');
}

function jctSender(array $over = []): ApprovedSender
{
    return ApprovedSender::create(array_merge([
        'label' => 'Japanese Car Trade', 'match_value' => 'inquiry@japanesecartrade.com', 'match_type' => ApprovedSender::MATCH_ADDRESS,
        'active' => true, 'use_reply_to' => true, 'brevo_list_ids' => [9], 'consent_mode' => ApprovedSender::CONSENT_DIRECT,
        'max_per_message' => 1, 'field_rules' => [], 'collect_buyer_details' => true,
    ], $over));
}

beforeEach(function () {
    MailerTest::fakeDns();
    MailerTest::brevoKey();
    $this->box = MailerTest::mailbox();
    DB::table('countries')->insert([
        ['iso2' => 'KE', 'name' => 'Kenya', 'slug' => 'kenya', 'is_active' => true],
        ['iso2' => 'GB', 'name' => 'United Kingdom', 'slug' => 'united-kingdom', 'is_active' => true],
    ]);
});

it('reads buyer and vehicle details from labelled lines, never from the buyer\'s message (TOC-BUY-001, TOC-LOG-003)', function () {
    $d = app(BuyerDetailsParser::class)->parse(jctMessage('m1', ['extra' => "Transmission : Automatic\nColor : White"]));

    expect($d)->toMatchArray([
        'kind' => 'stock', 'title' => 'Mr', 'first_name' => 'Jim', 'last_name' => 'Example',
        'country' => 'Kenya', 'country_code' => 'KE', 'port' => 'Mombasa', 'phone_e164' => '+254712345678',
        'buyer_type' => 'individual', 'make' => 'Toyota', 'model' => 'Hiace Van', 'year' => 2016, 'drive' => 'RHD', 'budget' => null,
    ])->and($d['details'])->toBe(['transmission' => 'Automatic', 'color' => 'White']);
    // "Budget : 999999" and "Name : Somebody Else" were typed inside the message: ignored.
});

it('recognises auction and buyer-request enquiries and portal references', function () {
    $p = app(BuyerDetailsParser::class);
    expect($p->parse(jctMessage('a', [], null, 'Auction Inquiry For Harley Davidson 1250s from Armenia'))['kind'])->toBe('auction')
        ->and($p->parse(jctMessage('b', [], null, 'Buyers Inquiry For Toyota Probox from Kenya'))['kind'])->toBe('request')
        ->and($p->parse(jctMessage('c', ['message' => 'about car ref : JCT-123456 please']))['details']['portal_refs'])->toBe(['JCT-123456']);
});

it('normalises countries, phones, names and buyer types (TOC-BUY-002)', function () {
    $n = app(BuyerNormalizer::class);
    expect($n->countryCode('UAE'))->toBe('AE')
        ->and($n->countryCode('United Kingdom'))->toBe('GB')
        ->and($n->countryCode('Jamaica'))->toBe('JM')          // from ICU names
        ->and($n->phoneE164('254712345678', 'KE'))->toBe('+254712345678')
        ->and($n->phoneE164('+44 7911 123456', null))->toBe('+447911123456')
        ->and($n->phoneE164('12345', 'KE'))->toBeNull()
        ->and($n->phoneE164('Not Shared', 'KE'))->toBeNull()
        ->and($n->phoneE164('+254+0712345678', 'KE'))->toBe('+254712345678')        // portal quirk: code + local number
        ->and($n->phoneE164('+234+2348031234567', 'NG'))->toBe('+2348031234567')     // doubled country code
        ->and($n->phoneE164('+44 07911123456', 'GB'))->toBe('+447911123456')         // trunk 0 after the code
        ->and($n->phoneE164('0712 345 678', 'KE'))->toBe('+254712345678')
        ->and($n->name('MRS. JANE   DOE SMITH'))->toBe(['title' => 'Mrs', 'first_name' => 'Jane', 'last_name' => 'Doe Smith'])
        ->and($n->name('Mr. Dr. Ali Hassan'))->toBe(['title' => 'Mr', 'first_name' => 'Ali', 'last_name' => 'Hassan'])   // two titles: used to loop forever
        ->and($n->name('Mr. Dr.'))->toBe(['title' => 'Mr', 'first_name' => null, 'last_name' => null])
        ->and($n->buyerType('Dealer/Importer'))->toBe('dealer')
        ->and($n->year('Mfg 1998'))->toBe(1998)
        ->and($n->year('3000'))->toBeNull();
});

it('records buyers and enquiries during import and fills Brevo name, country and phone (TOC-BUY-003/005)', function () {
    jctSender();
    $this->box->add(jctMessage('gm-1'));
    Http::fake([
        'api.brevo.com/v3/contacts/jim.example%40gmail.com' => Http::response(['message' => 'not found'], 404),
        'api.brevo.com/v3/contacts' => Http::response(['id' => 1], 201),
    ]);

    app(ImportRunner::class)->run();

    $buyer = Buyer::sole();
    expect($buyer->email)->toBe('jim.example@gmail.com')
        ->and($buyer->country_code)->toBe('KE')
        ->and($buyer->enquiry_count)->toBe(1)
        ->and($buyer->brevo_synced_at)->not->toBeNull()
        ->and(BuyerEnquiry::sole()->only(['make', 'model', 'year', 'kind']))->toBe(['make' => 'Toyota', 'model' => 'Hiace Van', 'year' => 2016, 'kind' => 'stock']);

    // Buyer attributes are not sent until mailer:brevo:setup has created them.
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://api.brevo.com/v3/contacts'
        && $r['attributes']['FIRSTNAME'] === 'Jim' && $r['attributes']['PHONE'] === '+254712345678' && $r['attributes']['COUNTRY'] === 'Kenya'
        && ! isset($r['attributes']['PORT']));
    expect(ContactImport::sole()->fields)->toMatchArray(['FIRSTNAME' => 'Jim', 'LASTNAME' => 'Example']);
});

it('sends buyer attributes to Brevo once they exist there (TOC-BUY-005)', function () {
    jctSender();
    app(MailerSettings::class)->set('brevo_buyer_attributes', true);
    $this->box->add(jctMessage('gm-1'));
    Http::fake([
        'api.brevo.com/v3/contacts/*' => Http::response(['email' => 'jim.example@gmail.com', 'attributes' => ['FIRSTNAME' => 'James'], 'listIds' => [], 'statistics' => []]),
        'api.brevo.com/v3/contacts' => Http::response(null, 204),
    ]);

    app(ImportRunner::class)->run();

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://api.brevo.com/v3/contacts'
        && $r['attributes']['PORT'] === 'Mombasa' && $r['attributes']['LAST_MAKE'] === 'Toyota' && $r['attributes']['ENQUIRY_COUNT'] === 1
        && ! isset($r['attributes']['FIRSTNAME']));   // rule 6: existing name kept
});

it('keeps the newest details and counts repeat enquiries; recording a message twice is a no-op (TOC-BUY-004)', function () {
    $sender = jctSender();
    $recorder = app(BuyerRecorder::class);
    $parse = fn ($m) => app(BuyerDetailsParser::class)->parse($m);

    $new = jctMessage('new', ['port' => 'Kisumu'], CarbonImmutable::parse('2026-09-01'));
    $old = jctMessage('old', ['port' => 'Mombasa', 'make' => 'Nissan', 'model' => 'Note'], CarbonImmutable::parse('2026-03-01'));
    $recorder->record('jim.example@gmail.com', $new, $sender, $parse($new));
    $recorder->record('jim.example@gmail.com', $old, $sender, $parse($old));   // backfill reads an older one later
    $recorder->record('jim.example@gmail.com', $old, $sender, $parse($old));

    $b = Buyer::sole();
    expect($b->port)->toBe('Kisumu')
        ->and($b->enquiry_count)->toBe(2)
        ->and($b->first_enquiry_at->toDateString())->toBe('2026-03-01')
        ->and($b->last_enquiry_at->toDateString())->toBe('2026-09-01')
        ->and($b->latestEnquiry->make)->toBe('Toyota');
});

it('backfills buyers from already-imported messages, read-only and resumable (TOC-BUY-006)', function () {
    $sender = jctSender();
    foreach (['p1', 'p2', 'gone'] as $id) {
        ProcessedMessage::create(['gmail_message_id' => $id, 'approved_sender_id' => $sender->id, 'received_at' => now(), 'outcome' => 'processed']);
    }
    $this->box->add(jctMessage('p1'));
    $this->box->add(jctMessage('p2', ['email' => 'ann@gmail.com', 'buyer' => 'Ms. Ann Other', 'country' => 'United Kingdom', 'phone' => '+44 7911 123456']));

    $backfill = app(BuyerBackfill::class);
    expect($backfill->readMessages(microtime(true) + 30, limit: 1))->toBe(1);
    expect($backfill->status()['messages_left'])->toBe(2);
    $backfill->readMessages(microtime(true) + 30);

    expect(Buyer::count())->toBe(2)
        ->and(ProcessedMessage::whereNull('buyer_scanned_at')->count())->toBe(0)
        ->and(Buyer::where('email', 'ann@gmail.com')->value('country_code'))->toBe('GB')
        ->and($this->box->fetches)->toBe(3);

    // Run again: nothing left to read.
    expect($backfill->readMessages(microtime(true) + 30))->toBe(0);
});

it('fills empty Brevo details for backfilled buyers without touching lists, source or names (TOC-BUY-006, rule 5/6)', function () {
    $sender = jctSender();
    app(MailerSettings::class)->set('brevo_buyer_attributes', true);
    $recorder = app(BuyerRecorder::class);
    $m = jctMessage('x');
    $recorder->record('jim.example@gmail.com', $m, $sender, app(BuyerDetailsParser::class)->parse($m));
    $m2 = jctMessage('y', ['email' => 'unsub@gmail.com']);
    $recorder->record('unsub@gmail.com', $m2, $sender, app(BuyerDetailsParser::class)->parse($m2));

    Http::fake([
        'api.brevo.com/v3/contacts/jim.example%40gmail.com' => Http::sequence()
            ->push(['email' => 'jim.example@gmail.com', 'attributes' => ['FIRSTNAME' => 'James'], 'statistics' => []])
            ->push(null, 204),
        'api.brevo.com/v3/contacts/unsub%40gmail.com' => Http::response(['emailBlacklisted' => true, 'attributes' => [], 'statistics' => []]),
    ]);

    $r = app(BuyerBackfill::class)->syncBrevo(microtime(true) + 30);

    expect($r)->toBe(['done' => 2, 'updated' => 1, 'skipped' => 1, 'failed' => 0]);
    Http::assertSent(fn (Request $q) => $q->method() === 'PUT' && str_contains($q->url(), 'jim.example')
        && ! isset($q['attributes']['FIRSTNAME']) && $q['attributes']['LASTNAME'] === 'Example' && $q['attributes']['PORT'] === 'Mombasa'
        && ! isset($q['listIds']) && ! isset($q['attributes']['SOURCE']));
    Http::assertNotSent(fn (Request $q) => $q->method() !== 'GET' && str_contains($q->url(), 'unsub'));
    expect(Buyer::whereNull('brevo_synced_at')->count())->toBe(0);
});

it('neutralises formula-like CSV cells but keeps phone numbers', function () {
    expect(BuyerExport::cell('=HYPERLINK("x")'))->toBe("'=HYPERLINK(\"x\")")
        ->and(BuyerExport::cell('+254712345678'))->toBe('+254712345678')
        ->and(BuyerExport::cell('Toyota'))->toBe('Toyota');
});

it('deletes buyers with no enquiry for 24 months (TOC-BUY-010)', function () {
    Buyer::create(['email' => 'old@gmail.com', 'last_enquiry_at' => now()->subMonths(25)]);
    Buyer::create(['email' => 'recent@gmail.com', 'last_enquiry_at' => now()->subMonths(2)]);

    $this->artisan('mailer:cleanup')->assertSuccessful();

    expect(Buyer::pluck('email')->all())->toBe(['recent@gmail.com']);
});

it('shows the buyer screens to Mailer users only (TOC-BUY-007 to 009)', function () {
    $this->seed(MailerPermissionSeeder::class);
    $sender = jctSender();
    $m = jctMessage('v');
    app(BuyerRecorder::class)->record('jim.example@gmail.com', $m, $sender, app(BuyerDetailsParser::class)->parse($m));
    $marketer = User::factory()->create();
    $marketer->assignRole(MailerAccess::ROLE_MARKETER);

    $this->actingAs($marketer);
    $this->get('/admin/mailer/buyers/list')->assertOk()->assertSee('jim.example@gmail.com');
    $this->get('/admin/mailer/buyers/list/'.Buyer::sole()->id)->assertOk()->assertSee('Mombasa')->assertSee('wa.me/254712345678', false);
    $this->get('/admin/mailer/buyers/demand')->assertOk()->assertSee('Most asked-for vehicles')->assertSee('Toyota Hiace Van');
    $this->get('/admin/mailer/buyers/matching')->assertOk()->assertSee('Buyers for a vehicle');

    Filament::setCurrentPanel('admin');
    Livewire::test(MatchingPage::class)
        ->set('make', 'TOYOTA')->set('model', 'hiace')->set('yearFrom', 2014)->set('yearTo', 2018)
        ->assertSee('1 matching buyer')->assertSee('Jim Example');
});

it('hides the buyer screens from users without Mailer access', function () {
    $this->seed(MailerPermissionSeeder::class);
    Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
    $this->actingAs(User::factory()->create()->assignRole('sales'));

    $this->get('/admin/mailer/buyers/list')->assertForbidden();
});
