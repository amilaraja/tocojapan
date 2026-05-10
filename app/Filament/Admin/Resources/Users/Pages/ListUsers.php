<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Support\Csv;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $query = $this->getFilteredTableQuery()->with(['roles', 'country']);

                    return Csv::download(
                        'users-'.now()->format('Ymd-His').'.csv',
                        ['name', 'email', 'phone', 'country', 'roles', 'last_login_at', 'created_at'],
                        $query->lazy()->map(fn ($u) => [
                            $u->name,
                            $u->email,
                            $u->phone,
                            $u->country?->name,
                            $u->roles->pluck('name')->implode('|'),
                            $u->last_login_at?->toIso8601String(),
                            $u->created_at?->toIso8601String(),
                        ])
                    );
                }),
        ];
    }
}
