<?php

use App\Models\User;
use App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder;
use App\Modules\Mailer\Filament\Widgets\ImportStats;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Models\ImportRun;
use App\Modules\Mailer\Support\MailerAccess;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(MailerPermissionSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(MailerAccess::ROLE_MARKETER);
    $this->actingAs($this->user);
});

it('counts contacts imported today, which goes up by 3 after a run that adds 3 (TOC-GEN-006)', function () {
    Livewire::test(ImportStats::class)->assertSee('Contacts imported today');

    $run = ImportRun::create(['trigger' => 'schedule', 'started_at' => now(), 'finished_at' => now(), 'status' => 'success', 'created' => 3]);
    foreach (['a@x.com', 'b@x.com', 'c@x.com'] as $email) {
        ContactImport::create(['email' => $email, 'run_id' => $run->id, 'outcome' => 'added', 'created_at' => now()]);
    }
    ContactImport::create(['email' => 'skip@x.com', 'run_id' => $run->id, 'outcome' => 'skipped', 'reason' => 'unsubscribed', 'created_at' => now()]);
    ContactImport::create(['email' => 'old@x.com', 'outcome' => 'added', 'created_at' => now()->subDays(10)]);

    $stats = (fn () => collect($this->getStats())->mapWithKeys(fn ($s) => [$s->getLabel() => $s->getValue()]))
        ->call(new ImportStats);

    expect($stats['Contacts imported today'])->toBe('3')
        ->and($stats['Last 7 days'])->toBe('3')
        ->and($stats['Last 30 days'])->toBe('4');
});

it('flags a failed last import in red', function () {
    ImportRun::create(['trigger' => 'schedule', 'started_at' => now(), 'status' => 'failed', 'error' => 'Connection timed out']);

    Livewire::test(ImportStats::class)->assertSee('Last import failed');
});

it('lists the 5 most recent campaigns with their status', function () {
    foreach (range(1, 6) as $i) {
        $c = Campaign::create(['name' => "Campaign {$i}", 'slug' => "campaign-{$i}", 'status' => $i === 6 ? 'sent' : 'draft']);
        $c->forceFill(['created_at' => now()->addMinutes($i)])->save();
    }

    $this->get('/admin/mailer')
        ->assertOk()
        ->assertSee('Campaign 6')
        ->assertSee('Campaign 2')
        ->assertDontSee('Campaign 1')
        ->assertSee('Sent');
});

it('shows a friendly empty state with no campaigns', function () {
    $this->get('/admin/mailer')->assertOk()->assertSee('No campaigns yet');
});
