<?php

use App\Models\User;
use App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder;
use App\Modules\Mailer\Support\MailerAccess;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(MailerPermissionSeeder::class);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
});

function mailerUser(string ...$roles): User
{
    $user = User::factory()->create();
    $user->assignRole($roles);

    return $user;
}

it('redirects guests on every Mailer URL to the admin login (TOC-GEN-001)', function (string $url) {
    $this->get($url)->assertRedirect('/admin/login');
})->with(['/admin/mailer', '/admin/mailer/settings']);

it('lets a Marketer open the overview but returns 403 on settings (TOC-GEN-002)', function () {
    $marketer = mailerUser(MailerAccess::ROLE_MARKETER);

    $this->actingAs($marketer)->get('/admin/mailer')->assertOk();
    $this->actingAs($marketer)->get('/admin/mailer/settings')->assertForbidden();
});

it('lets a Mailer Admin open settings', function () {
    $this->actingAs(mailerUser(MailerAccess::ROLE_ADMIN))
        ->get('/admin/mailer/settings')
        ->assertOk()
        ->assertSee('Brevo connection');
});

it('gives super_admin both Mailer permissions', function () {
    $user = mailerUser('super_admin');

    expect(MailerAccess::isAdmin($user))->toBeTrue()
        ->and(MailerAccess::canUse($user))->toBeTrue();
});

it('returns 403 on all Mailer URLs once both Mailer roles are removed (TOC-GEN-003)', function () {
    $user = mailerUser('admin', MailerAccess::ROLE_ADMIN);
    $this->actingAs($user)->get('/admin/mailer/settings')->assertOk();

    $user->removeRole(MailerAccess::ROLE_ADMIN);
    $user->refresh();

    $this->actingAs($user)->get('/admin/mailer')->assertForbidden();
    $this->actingAs($user)->get('/admin/mailer/settings')->assertForbidden();
});

// One user per test: Filament builds the navigation once per app instance.
it('hides the Mailer menu from users without a Mailer permission (TOC-GEN-004)', function () {
    $this->actingAs(mailerUser('admin'))
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('/admin/mailer', false);
});

it('shows Marketers the Mailer menu without Mailer settings (TOC-GEN-004)', function () {
    $this->actingAs(mailerUser('sales', MailerAccess::ROLE_MARKETER))
        ->get('/admin')
        ->assertOk()
        ->assertSee('/admin/mailer', false)
        ->assertDontSee('/admin/mailer/settings', false);
});

it('shows Mailer Admins the Mailer settings menu item (TOC-GEN-004)', function () {
    $this->actingAs(mailerUser(MailerAccess::ROLE_ADMIN))
        ->get('/admin')
        ->assertOk()
        ->assertSee('/admin/mailer/settings', false);
});

it('lets a Mailer-only user into the admin panel', function () {
    $this->actingAs(mailerUser(MailerAccess::ROLE_MARKETER))->get('/admin')->assertOk();
});
