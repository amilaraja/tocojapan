<?php

namespace App\Filament\Admin\Resources\NotFoundLogs\Tables;

use App\Models\Redirect;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotFoundLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('path')
                    ->searchable()
                    ->wrap()
                    ->description(fn (\App\Models\NotFoundLog $record): ?string => $record->referer ? 'from: '.$record->referer : null),
                TextColumn::make('hits')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent')
                    ->label('User agent')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('createRedirect')
                    ->label('Create redirect')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->schema([
                        TextInput::make('to_path')
                            ->label('Redirect this URL to')
                            ->placeholder('/vehicles  or  https://example.com/page')
                            ->required()
                            ->maxLength(1000),
                        Select::make('status_code')
                            ->label('Redirect type')
                            ->options([301 => '301 — Permanent', 302 => '302 — Temporary'])
                            ->default(301)
                            ->required(),
                    ])
                    ->action(function (\App\Models\NotFoundLog $record, array $data): void {
                        Redirect::updateOrCreate(
                            ['from_path' => $record->path === '/' ? '' : ltrim($record->path, '/')],
                            [
                                'to_path' => $data['to_path'],
                                'status_code' => (int) $data['status_code'],
                                'is_active' => true,
                            ],
                        );
                        $record->delete();

                        Notification::make()->title('Redirect created')->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
