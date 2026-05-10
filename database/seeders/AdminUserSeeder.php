<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@tocojapan.com'],
            [
                'name' => 'Toco Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }
    }
}
