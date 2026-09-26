<?php

use App\Models\User;
use App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder;
use App\Modules\Mailer\Filament\Pages\Importer\BackfillPage;
use App\Modules\Mailer\Filament\Pages\Importer\ImporterStatus;
use App\Modules\Mailer\Filament\Pages\Importer\RuleTester;
use App\Modules\Mailer\Filament\Pages\MailerSettingsPage;
use App\Modules\Mailer\Filament\Resources\ApprovedSenders\Pages\CreateApprovedSender;
use App\Modules\Mailer\Filament\Resources\ContactImports\Pages\ManageContactImports;
use App\Modules\Mailer\Filament\Resources\IgnoreRules\Pages\ManageIgnoreRules;
use App\Modules\Mailer\Jobs\RunBackfillBatch;
use App\Modules\Mailer\Jobs\RunImport;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Models\IgnoreRule;
use App\Modules\Mailer\Models\ProcessedMessage;
use App\Modules\Mailer\Support\MailerAccess;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Mailer\Support\FakeMailbox;
use Tests\Mailer\Support\MailerTest;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(MailerPermissionSeeder::class);
    MailerTest::fakeDns();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(MailerAccess::ROLE_ADMIN);
});

it('renders every Importer screen for a Mailer Admin', function (string $url, string $see) {
    MailerTest::tocoSender();
    $this->actingAs($this->admin)->get($url)->assertOk()->assertSee($see);
})->with([
    ['/admin/mailer/importer/status', 'Inbox importer'],
    ['/admin/mailer/importer/senders', 'Website inquiry form'],
    ['/admin/mailer/importer/senders/create', 'Messages from'],
    ['/admin/mailer/importer/ignore-list', 'Ignore list'],
    ['/admin/mailer/importer/run-log', 'Run log'],
    ['/admin/mailer/importer/contact-search', 'Contact search'],
    ['/admin/mailer/importer/backfill', 'Import older messages'],
    ['/admin/mailer/importer/rule-tester', 'Test only, nothing is saved'],
]);

it('returns 403 on every Importer screen for a Marketer (TOC-GEN-002)', function (string $url) {
    $marketer = User::factory()->create();
    $marketer->assignRole(MailerAccess::ROLE_MARKETER);

    $this->actingAs($marketer)->get($url)->assertForbidden();
})->with([
    '/admin/mailer/importer/status', '/admin/mailer/importer/senders', '/admin/mailer/importer/ignore-list',
    '/admin/mailer/importer/run-log', '/admin/mailer/importer/contact-search', '/admin/mailer/importer/backfill',
    '/admin/mailer/importer/rule-tester',
]);

it('creates an approved sender for a whole domain (TOC-EXT-001)', function () {
    Cache::put('mailer:brevo:lists', [7 => 'Buyers (10)', 8 => 'Leads (3)'], 60);
    MailerTest::brevoKey();
    $this->actingAs($this->admin);

    Livewire::test(CreateApprovedSender::class)
        ->fillForm([
            'label' => 'Example portal', 'match_value' => ' @Example-Portal.com ', 'active' => true,
            'max_per_message' => 3, 'brevo_list_ids' => [8], 'consent_mode' => 'direct',
            'field_rules' => [['field' => 'name', 'pattern' => 'Name: (.+)']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sender = ApprovedSender::sole();
    expect($sender->match_value)->toBe('@example-portal.com')
        ->and($sender->match_type)->toBe('domain')
        ->and($sender->brevo_list_ids)->toBe([8]);
});

it('rejects a bad sender value, a broken field rule, and Confirm first without its settings', function () {
    Cache::put('mailer:brevo:lists', [7 => 'Buyers'], 60);
    MailerTest::brevoKey();
    $this->actingAs($this->admin);

    Livewire::test(CreateApprovedSender::class)
        ->fillForm([
            'label' => 'Bad', 'match_value' => 'not-an-address', 'max_per_message' => 3, 'brevo_list_ids' => [7],
            'consent_mode' => 'confirm', 'field_rules' => [['field' => 'name', 'pattern' => 'Name: .+']],
        ])
        ->call('create')
        ->assertHasFormErrors(['match_value', 'doi_template_id', 'doi_redirect_url', 'field_rules.0.pattern']);
});

it('adds to the ignore list with the right type (TOC-EXT-009)', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageIgnoreRules::class)
        ->callAction('create', ['value' => '@Competitor.JP', 'note' => 'Rival'])
        ->assertHasNoActionErrors();

    $rule = IgnoreRule::sole();
    expect($rule->value)->toBe('@competitor.jp')
        ->and($rule->type)->toBe('domain')
        ->and($rule->created_by)->toBe($this->admin->id);
});

it('tests rules with no Brevo request and no import record (TOC-EXT-008)', function () {
    Http::fake();
    $sender = MailerTest::tocoSender();
    $this->actingAs($this->admin);

    Livewire::test(RuleTester::class)
        ->set('data.sender_id', $sender->id)
        ->set('data.message', FakeMailbox::fixture('tocojapan-inquiry/01-basic.eml'))
        ->call('test')
        ->assertHasNoErrors()
        ->assertSee('adam.steiger@gmail.com')
        ->assertSee('Would be imported')
        ->assertSee('Steiger');

    Http::assertNothingSent();
    expect(ContactImport::count())->toBe(0)->and(ProcessedMessage::count())->toBe(0);
});

it('queues a manual run from Run now (TOC-IMP-003)', function () {
    Queue::fake();
    $this->actingAs($this->admin);

    Livewire::test(ImporterStatus::class)->callAction('runNow');

    Queue::assertPushed(RunImport::class, fn (RunImport $job) => $job->trigger === 'manual' && $job->queue === 'mailer');
});

it('starts a backfill from the screen', function () {
    Queue::fake();
    $this->actingAs($this->admin);

    Livewire::test(BackfillPage::class)->callAction('start', ['from' => '2026-01-01']);

    Queue::assertPushed(RunBackfillBatch::class);
});

it('retries a failed import for Admins (TOC-BRV-005)', function () {
    MailerTest::brevoKey();
    Http::fake(fn ($r) => $r->method() === 'GET' ? Http::response([], 404) : Http::response(['id' => 3], 201));
    $sender = MailerTest::tocoSender();
    $failed = ContactImport::create(['email' => 'x@buyers.com', 'approved_sender_id' => $sender->id, 'outcome' => 'failed', 'reason' => 'brevo_error', 'brevo_status_code' => 400, 'created_at' => now()]);
    $this->actingAs($this->admin);

    Livewire::test(ManageContactImports::class)->callTableAction('retry', $failed);

    expect(ContactImport::latest('id')->first())->outcome->toBe('added')->email->toBe('x@buyers.com');
});

it('reports Brevo connection OK or failed on the settings screen', function () {
    MailerTest::brevoKey();
    $this->actingAs($this->admin);

    Http::fake(['*' => Http::response(['companyName' => 'TOCO International'])]);
    Livewire::test(MailerSettingsPage::class)->callAction('testBrevo')->assertNotified('Connection OK');
});

it('shows a failed Brevo connection', function () {
    MailerTest::brevoKey();
    $this->actingAs($this->admin);

    Http::fake(['*' => Http::response(['message' => 'We have detected you are using an unrecognised IP address 172.104.62.81.'], 401)]);
    Livewire::test(MailerSettingsPage::class)->callAction('testBrevo')->assertNotified(
        \Filament\Notifications\Notification::make()->title('Connection failed')->danger()->persistent()
            ->body("Brevo blocked this server's address (172.104.62.81). In Brevo, open Security, Authorised IPs and add 172.104.62.81, then try again."),
    );
});
