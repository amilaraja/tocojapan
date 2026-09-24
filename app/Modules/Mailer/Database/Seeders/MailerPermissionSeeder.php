<?php

namespace App\Modules\Mailer\Database\Seeders;

use App\Modules\Mailer\Support\MailerAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * TOC-GEN-002/003. Idempotent: safe to run on every deploy.
 *
 * Plain admin and sales get no Mailer access until a Mailer role is added
 * on the Users screen (TOC-GEN-004). super_admin gets both.
 */
class MailerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Permission::firstOrCreate(['name' => MailerAccess::ADMIN, 'guard_name' => 'web']);
        $marketer = Permission::firstOrCreate(['name' => MailerAccess::MARKETER, 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => MailerAccess::ROLE_ADMIN, 'guard_name' => 'web'])
            ->syncPermissions([$admin, $marketer]);

        Role::firstOrCreate(['name' => MailerAccess::ROLE_MARKETER, 'guard_name' => 'web'])
            ->syncPermissions([$marketer]);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
            ->givePermissionTo([$admin, $marketer]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
