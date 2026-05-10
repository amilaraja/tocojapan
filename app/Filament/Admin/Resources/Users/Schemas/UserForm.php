<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('email')->email()->required()->unique(table: 'users', ignoreRecord: true),
                    TextInput::make('phone')->tel()->maxLength(40),
                    Select::make('country_id')->relationship('country', 'name')->searchable(),
                    TextInput::make('locale')->maxLength(10)->default('en'),
                    TextInput::make('preferred_currency')->maxLength(3)->default('USD'),
                ]),

            Section::make('Roles')
                ->description('A user with no admin/sales/super_admin role cannot access this panel.')
                ->schema([
                    Select::make('roles')
                        ->multiple()
                        ->relationship('roles', 'name')
                        ->options(self::availableRoles())
                        ->preload(),
                ]),

            Section::make('Set or change password')
                ->description('Leave blank to keep the existing password unchanged.')
                ->schema([
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->dehydrateStateUsing(fn (string $state) => Hash::make($state)),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function availableRoles(): array
    {
        $current = auth()->user();
        $roles = Role::where('guard_name', 'web')->orderBy('name')->pluck('name', 'name')->all();

        // Only super_admin can grant the super_admin role.
        if (! $current?->hasRole('super_admin')) {
            unset($roles['super_admin']);
        }

        return $roles;
    }
}
