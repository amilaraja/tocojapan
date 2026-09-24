<?php

use App\Models\User;
use App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder;
use App\Modules\Mailer\Filament\Pages\MailerSettingsPage;
use App\Modules\Mailer\Models\MailerSetting;
use App\Modules\Mailer\Support\MailerAccess;
use App\Modules\Mailer\Support\MailerSettings;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(MailerPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(MailerAccess::ROLE_ADMIN);
});

it('stores the Brevo key encrypted and only ever shows the last 4 characters (TOC-NFR-001)', function () {
    $this->actingAs($this->admin);

    Livewire::test(MailerSettingsPage::class)
        ->set('data.brevo_api_key', 'xkeysib-secret-value-9876')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('data.brevo_api_key', null);

    $row = MailerSetting::where('key', 'brevo_api_key')->first();
    expect($row->encrypted)->toBeTrue()
        ->and($row->value)->not->toContain('secret-value');

    $fresh = new MailerSettings;
    expect($fresh->brevoApiKey())->toBe('xkeysib-secret-value-9876')
        ->and($fresh->masked('brevo_api_key'))->toBe('••••9876');

    $this->get('/admin/mailer/settings')
        ->assertOk()
        ->assertSee('••••9876')
        ->assertDontSee('secret-value');
});

it('keeps the saved Brevo key when the field is left blank', function () {
    $this->actingAs($this->admin);
    app(MailerSettings::class)->set('brevo_api_key', 'key-ending-1234');

    Livewire::test(MailerSettingsPage::class)
        ->set('data.fraud_text', 'Changed warning.')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = new MailerSettings;
    expect($fresh->brevoApiKey())->toBe('key-ending-1234')
        ->and($fresh->get('fraud_text'))->toBe('Changed warning.');
});

it('rejects an import interval outside 5 to 1440 minutes (TOC-IMP-002)', function (int $minutes) {
    $this->actingAs($this->admin);

    Livewire::test(MailerSettingsPage::class)
        ->set('data.import_interval_minutes', $minutes)
        ->call('save')
        ->assertHasErrors(['data.import_interval_minutes']);
})->with([4, 1441]);

it('rejects a Google key path inside the public folder', function () {
    $this->actingAs($this->admin);

    Livewire::test(MailerSettingsPage::class)
        ->set('data.google_key_path', public_path('toco-gmail-sa.json'))
        ->call('save')
        ->assertHasErrors(['data.google_key_path']);
});

it('normalises TOCO own domains', function () {
    $this->actingAs($this->admin);

    Livewire::test(MailerSettingsPage::class)
        ->set('data.own_domains', [' @TocoJapan.com ', 'toco-iont.com', 'tocojapan.com'])
        ->call('save')
        ->assertHasNoErrors();

    expect((new MailerSettings)->get('own_domains'))->toBe(['tocojapan.com', 'toco-iont.com']);
});
