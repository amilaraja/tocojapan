<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'sales' => 'info',
                        'customer' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('phone')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country.name')->label('Country')->toggleable(),
                TextColumn::make('last_login_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
                TextColumn::make('email_verified_at')->label('Verified')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(fn () => Role::orderBy('name')->pluck('name', 'name')->all())
                    ->query(function ($query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('roles', fn ($q) => $q->where('name', $data['value']));
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('sendPasswordReset')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        Password::sendResetLink(['email' => $record->email]);
                        Notification::make()->title('Password reset email sent.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
