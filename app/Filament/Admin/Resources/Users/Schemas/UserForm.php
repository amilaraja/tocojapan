<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

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
                ->description('Website admin: super_admin, admin, sales, mailer roles. Sales team (CRM at /crm only): crm_agent, crm_team_lead, crm_operations, crm_admin.')
                ->schema([
                    // Options come from the relationship (keyed by role id) so the
                    // chosen roles validate and save; super_admin only for super admins.
                    Select::make('roles')
                        ->multiple()
                        ->relationship('roles', 'name', modifyQueryUsing: fn ($query) => $query
                            ->where('guard_name', 'web')
                            ->when(! auth()->user()?->hasRole('super_admin'), fn ($query) => $query->where('name', '!=', 'super_admin'))
                            ->orderBy('name'))
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
}
