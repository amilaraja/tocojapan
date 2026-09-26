<?php

use App\Models\User;
use App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder;
use App\Modules\Mailer\Domain\Importer\Backfill;
use App\Modules\Mailer\Domain\Importer\GmailReader;
use App\Modules\Mailer\Domain\Importer\ImportRunner;
use App\Modules\Mailer\Domain\Importer\ParsedMessage;
use App\Modules\Mailer\Jobs\RunBackfillBatch;
use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Models\ImportRun;
use App\Modules\Mailer\Models\ImportState;
use App\Modules\Mailer\Models\ProcessedMessage;
use App\Modules\Mailer\Notifications\ImportFailingAlert;
use App\Modules\Mailer\Support\MailerAccess;
use App\Modules\Mailer\Support\MailerSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Mailer\Support\FakeMailbox;
use Tests\Mailer\Support\MailerTest;

beforeEach(function () {
    MailerTest::fakeDns();
    MailerTest::brevoKey();
    app(MailerSettings::class)->setMany(['own_domains' => ['tocojapan.com'], 'mailbox' => 'first@toco-iont.com']);
    $this->box = MailerTest::mailbox();
    $this->sender = MailerTest::tocoSender();

    // Brevo: every contact is new and created, unless the test takes Brevo down.
    $this->brevoDown = false;
    $this->brevo401 = false;
    Http::fake(function ($request) {
        if ($this->brevoDown) {
            return Http::response([], 503);
        }
        if ($this->brevo401) {
            return Http::response(['message' => 'We have detected you are using an unrecognised IP address 1.2.3.4.'], 401);
        }

        return $request->method() === 'GET' ? Http::response([], 404) : Http::response(['id' => 1], 201);
    });
});

function inquiry(string $id, string $buyer, ?CarbonImmutable $at = null, string $from = 'info@tocojapan.com'): ParsedMessage
{
    return new ParsedMessage($id, $from, null, 'New website inquiry', $at ?? CarbonImmutable::now()->subHour(), "From: Buyer {$id} <{$buyer}>\nPhone: 123", '');
}

function runner(): ImportRunner
{
    return app(ImportRunner::class);
}

it('imports from the fixture and writes the run log and audit (UAT-1, TOC-LOG-001/002)', function () {
    $this->box->addEml(FakeMailbox::fixture('tocojapan-inquiry/01-basic.eml'), 'gm-1');
    $this->box->messages['gm-1'] = new ParsedMessage('gm-1', 'info@tocojapan.com', null, 's', CarbonImmutable::now()->subHour(), $this->box->messages['gm-1']->text, $this->box->messages['gm-1']->html);

    $run = runner()->run();

    expect($run->status)->toBe('success')
        ->and($run->trigger)->toBe('schedule')
        ->and($run->scanned)->toBe(1)
        ->and($run->created)->toBe(1)
        ->and($run->finished_at)->not->toBeNull();

    $audit = ContactImport::sole();
    expect($audit->email)->toBe('adam.steiger@gmail.com')
        ->and($audit->gmail_message_id)->toBe('gm-1')
        ->and($audit->approvedSender->label)->toBe('Website inquiry form')
        ->and($audit->outcome)->toBe('added')
        ->and($audit->brevo_status_code)->toBe(201)
        ->and($audit->fields)->toMatchArray(['FIRSTNAME' => 'Adam', 'STOCK_REF' => 'E02056']);
});

it('processes only approved senders and leaves no record of others (TOC-IMP-004)', function () {
    $this->box->add(inquiry('a', 'one@buyers.com'));
    $this->box->add(inquiry('b', 'two@buyers.com', null, 'spam@elsewhere.com'));

    $run = runner()->run();

    expect($run->scanned)->toBe(1)
        ->and(ProcessedMessage::pluck('gmail_message_id')->all())->toBe(['a'])
        ->and(ContactImport::where('gmail_message_id', 'b')->exists())->toBeFalse()
        ->and($this->box->queries[0])->toStartWith('from:(info@tocojapan.com) after:');
});

it('processes each message once, ever (TOC-IMP-005)', function () {
    $this->box->add(inquiry('a', 'one@buyers.com'));
    $this->box->add(inquiry('b', 'two@buyers.com'));

    runner()->run();
    $second = runner()->run();

    expect($second->scanned)->toBe(0)
        ->and(ProcessedMessage::count())->toBe(2)
        ->and(ContactImport::count())->toBe(2);
});

it('loses nothing when a run fails midway, and keeps the checkpoint (TOC-IMP-006)', function () {
    foreach (['a', 'b', 'c'] as $i => $id) {
        $this->box->add(inquiry($id, "{$id}@buyers.com", CarbonImmutable::now()->subMinutes(30 - $i)));
    }
    $this->box->failOnFetchNumber = 2;

    $failed = runner()->run();

    expect($failed->status)->toBe('failed')
        ->and($failed->error)->toContain('mailbox could not be read')
        ->and(ImportState::sole()->last_checkpoint_at)->toBeNull()
        ->and(ImportState::sole()->lock_until)->toBeNull();

    $this->box->failOnFetchNumber = 0;
    $ok = runner()->run();

    expect($ok->status)->toBe('success')
        ->and(ProcessedMessage::pluck('gmail_message_id')->sort()->values()->all())->toBe(['a', 'b', 'c'])
        ->and(ContactImport::count())->toBe(3);
});

it('rolls back a message when Brevo is unreachable and retries it next run', function () {
    $this->box->add(inquiry('a', 'one@buyers.com'));
    $this->brevoDown = true;

    $failed = runner()->run();
    expect($failed->status)->toBe('failed')
        ->and($failed->error)->toContain('Brevo is busy')
        ->and(ProcessedMessage::count())->toBe(0)
        ->and(ContactImport::count())->toBe(0);

    $this->brevoDown = false;
    expect(runner()->run()->created)->toBe(1)
        ->and(ProcessedMessage::count())->toBe(1);
});

it('fails the run (not each contact) on a Brevo 401, keeping the message for the next run', function () {
    $this->box->add(inquiry('a', 'one@buyers.com'));
    $this->brevo401 = true;

    $run = runner()->run();

    expect($run->status)->toBe('failed')
        ->and($run->error)->toContain('Authorised IPs')
        ->and(ContactImport::count())->toBe(0)
        ->and(ProcessedMessage::count())->toBe(0);

    $this->brevo401 = false;
    expect(runner()->run()->created)->toBe(1);
});

it('stops at the time budget, keeps the checkpoint, and the next run carries on', function () {
    config(['mailer.import.run_seconds' => 25]);
    foreach (range(1, 5) as $i) {
        $this->box->add(inquiry("m{$i}", "b{$i}@buyers.com", CarbonImmutable::now()->subMinutes(60 - $i)));
    }
    $this->box->secondsPerFetch = 10;

    $first = runner()->run();
    expect($first->status)->toBe('success')
        ->and($first->scanned)->toBe(3)
        ->and($first->error)->toBe(ImportRunner::MORE_WAITING)
        ->and(ImportState::sole()->last_checkpoint_at)->toBeNull()
        ->and(ProcessedMessage::orderBy('id')->pluck('gmail_message_id')->all())->toBe(['m1', 'm2', 'm3']);

    $second = runner()->run();
    expect($second->scanned)->toBe(2)
        ->and($second->error)->toBeNull()
        ->and(ImportState::sole()->last_checkpoint_at)->not->toBeNull()
        ->and(ProcessedMessage::count())->toBe(5);
});

it('repeats a backfill page that ran out of time instead of skipping ahead', function () {
    Queue::fake();
    config(['mailer.import.run_seconds' => 25]);
    foreach (range(1, 5) as $i) {
        $this->box->add(inquiry("m{$i}", "b{$i}@buyers.com", CarbonImmutable::parse('2026-06-01')->addHours($i)));
    }
    $this->box->secondsPerFetch = 10;
    app(Backfill::class)->start(CarbonImmutable::parse('2026-05-01'));

    runner()->backfillBatch();
    expect(runner()->state()->backfill_cursor)->toMatchArray(['batches_done' => 0, 'page_token' => null, 'messages_seen' => 3, 'status' => 'running']);

    runner()->backfillBatch();
    expect(runner()->state()->backfill_cursor)->toMatchArray(['batches_done' => 1, 'messages_seen' => 5, 'status' => 'done'])
        ->and(ProcessedMessage::count())->toBe(5);
});

it('closes a run left at "running" by a killed worker', function () {
    $stale = ImportRun::create(['trigger' => 'backfill', 'started_at' => now()->subMinutes(10), 'status' => 'running']);
    runner()->state()->forceFill(['lock_until' => now()->subMinute()])->save();

    runner()->run();

    expect($stale->fresh()->status)->toBe('failed')
        ->and($stale->fresh()->error)->toContain('Stopped before finishing');
});

it('skips a run while another is active (TOC-IMP-007)', function () {
    $state = runner()->state();
    $state->forceFill(['lock_until' => now()->addMinutes(5)])->save();
    $this->box->add(inquiry('a', 'one@buyers.com'));

    $run = runner()->run(ImportRun::TRIGGER_MANUAL);

    expect($run->status)->toBe('skipped')
        ->and($run->error)->toBe('skipped: already running')
        ->and(ProcessedMessage::count())->toBe(0);
});

it('takes the lock over when it has expired', function () {
    runner()->state()->forceFill(['lock_until' => now()->subMinute()])->save();

    expect(runner()->run()->status)->toBe('success');
});

it('uses Gmail history when it has a history id, filtering by sender from headers only', function () {
    runner()->state()->forceFill(['last_history_id' => '900'])->save();
    $this->box->add(inquiry('a', 'one@buyers.com'));
    $this->box->add(inquiry('b', 'two@buyers.com', null, 'other@x.com'));
    $this->box->historyIds = ['a', 'b'];

    $run = runner()->run();

    expect($run->scanned)->toBe(1)
        ->and($this->box->queries)->toBe([])
        ->and($this->box->fetches)->toBe(1)
        ->and(ImportState::sole()->last_history_id)->toBe('1000');
});

it('falls back to a search when the history id has expired', function () {
    runner()->state()->forceFill(['last_history_id' => '1'])->save();
    $this->box->historyIds = null;
    $this->box->add(inquiry('a', 'one@buyers.com'));

    expect(runner()->run()->scanned)->toBe(1)->and($this->box->queries)->toHaveCount(1);
});

it('emails each Mailer Admin exactly once after 3 failures, and again only after a success (TOC-IMP-009)', function () {
    Notification::fake();
    $this->seed(MailerPermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(MailerAccess::ROLE_ADMIN);
    $marketer = User::factory()->create();
    $marketer->assignRole(MailerAccess::ROLE_MARKETER);

    $this->box->down = true;
    foreach (range(1, 5) as $_) {
        runner()->run();
    }
    Notification::assertSentToTimes($admin, ImportFailingAlert::class, 1);
    Notification::assertNotSentTo($marketer, ImportFailingAlert::class);

    $this->box->down = false;
    runner()->run();
    $this->box->down = true;
    foreach (range(1, 3) as $_) {
        runner()->run();
    }
    Notification::assertSentToTimes($admin, ImportFailingAlert::class, 2);
});

it('backfills 250 messages in 3 batches and resumes after the worker stops (TOC-IMP-008)', function () {
    Queue::fake();
    foreach (range(1, 250) as $i) {
        $this->box->add(inquiry("m{$i}", "b{$i}@buyers.com", CarbonImmutable::parse('2026-06-01')->addHours($i)));
    }

    app(Backfill::class)->start(CarbonImmutable::parse('2026-05-01'));
    Queue::assertPushed(RunBackfillBatch::class, 1);

    // Batch 1, then the worker "stops": the queued follow-up job is lost.
    runner()->backfillBatch();
    expect(runner()->state()->backfill_cursor)->toMatchArray(['batches_done' => 1, 'status' => 'running'])
        ->and(ProcessedMessage::count())->toBe(100);

    // Worker restarted: resume and run the remaining batches.
    app(Backfill::class)->resume();
    runner()->backfillBatch();
    runner()->backfillBatch();

    $cursor = runner()->state()->backfill_cursor;
    expect($cursor)->toMatchArray(['batches_done' => 3, 'status' => 'done', 'messages_seen' => 250])
        ->and(ProcessedMessage::count())->toBe(250)
        ->and(ImportRun::where('trigger', 'backfill')->where('status', 'success')->count())->toBe(3)
        ->and(runner()->backfillBatch())->toBeNull();
});

it('resets the failure count after a successful backfill batch too', function () {
    Queue::fake();
    $this->box->add(inquiry('m1', 'b1@buyers.com', CarbonImmutable::parse('2026-06-01')));
    runner()->state()->forceFill(['consecutive_failures' => 2])->save();
    app(Backfill::class)->start(CarbonImmutable::parse('2026-05-01'));

    runner()->backfillBatch();

    expect(runner()->state()->consecutive_failures)->toBe(0);
});

it('pauses a backfill', function () {
    Queue::fake();
    app(Backfill::class)->start(CarbonImmutable::parse('2026-05-01'));
    app(Backfill::class)->pause();

    expect(runner()->backfillBatch())->toBeNull()
        ->and(runner()->state()->backfill_cursor['status'])->toBe('paused');
});

it('respects the interval between scheduled runs (TOC-IMP-002)', function () {
    app(MailerSettings::class)->set('import_interval_minutes', 30);

    $this->artisan('mailer:import')->assertSuccessful();
    expect(ImportRun::count())->toBe(1);

    $this->travel(20)->minutes();
    $this->artisan('mailer:import')->assertSuccessful();
    expect(ImportRun::count())->toBe(1);

    $this->travel(10)->minutes();
    $this->artisan('mailer:import')->assertSuccessful();
    expect(ImportRun::count())->toBe(2);

    $this->artisan('mailer:import --now')->assertSuccessful();
    expect(ImportRun::latest('id')->first()->trigger)->toBe('manual');
});

it('deletes run logs and audit records older than 12 months (TOC-LOG-004)', function () {
    $old = ImportRun::create(['trigger' => 'schedule', 'started_at' => now()->subMonths(13), 'status' => 'success']);
    ContactImport::create(['email' => 'old@x.com', 'outcome' => 'added', 'run_id' => $old->id, 'created_at' => now()->subMonths(13)]);
    ContactImport::create(['email' => 'new@x.com', 'outcome' => 'added', 'created_at' => now()->subMonths(11)]);

    $this->artisan('mailer:cleanup')->assertSuccessful();

    expect(ContactImport::pluck('email')->all())->toBe(['new@x.com'])
        ->and(ImportRun::find($old->id))->toBeNull();
});

it('has no column that could hold a message body (TOC-LOG-003)', function () {
    $tables = ['mailer_processed_messages', 'mailer_contact_imports', 'mailer_import_runs', 'mailer_import_state'];
    $columns = collect($tables)->flatMap(fn ($t) => Schema::getColumnListing($t))->all();

    expect(array_filter($columns, fn ($c) => preg_match('/body|content|html|text|raw|attachment|snippet/i', $c)))->toBe([]);
});

it('asks Google for gmail.readonly and nothing else (TOC-IMP-001)', function () {
    $key = tempnam(sys_get_temp_dir(), 'sa');
    file_put_contents($key, json_encode([
        'type' => 'service_account', 'client_email' => 'svc@toco.iam.gserviceaccount.com', 'client_id' => '1',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIB\n-----END PRIVATE KEY-----\n", 'private_key_id' => 'k',
    ]));
    app(MailerSettings::class)->setMany(['google_key_path' => $key, 'mailbox' => 'first@toco-iont.com']);

    $client = app(GmailReader::class)->client();

    expect($client->getScopes())->toBe(['https://www.googleapis.com/auth/gmail.readonly'])
        ->and($client->getConfig('subject'))->toBe('first@toco-iont.com');
});
