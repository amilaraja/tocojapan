<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permission:cache-reset');

        $permissions = [
            'admin.access',
            'vehicles.view', 'vehicles.create', 'vehicles.update', 'vehicles.delete', 'vehicles.publish',
            'pages.view', 'pages.create', 'pages.update', 'pages.delete',
            'orders.view', 'orders.update',
            'customers.view', 'customers.update',
            'settings.update',
            'users.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $customer = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $superAdmin->syncPermissions(Permission::all());

        $admin->syncPermissions(array_diff($permissions, ['users.manage']));

        $sales->syncPermissions([
            'admin.access',
            'vehicles.view', 'vehicles.update',
            'orders.view', 'orders.update',
            'customers.view', 'customers.update',
        ]);

        // Customer role: app-side only, no admin.access permission.
    }
}
