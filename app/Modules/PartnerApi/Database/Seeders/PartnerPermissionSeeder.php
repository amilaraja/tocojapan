<?php

namespace App\Modules\PartnerApi\Database\Seeders;

use App\Modules\PartnerApi\Support\PartnerAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * partners.admin (UI brief section 1), given to super_admin and admin, the
 * business owners. Sales and Mailer roles get nothing. Idempotent.
 */
class PartnerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => PartnerAccess::ADMIN, 'guard_name' => 'web']);

        foreach (['super_admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
