<?php

namespace App\Filament\Admin\Resources\Pages\Tables;

use App\Cms\PageTemplateRegistry;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()
                    ->prefix('/')
                    ->copyable(),
                TextColumn::make('template_key')->label('Template')
                    ->formatStateUsing(fn (string $state) => PageTemplateRegistry::options()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('locale')->toggleable(),
                TextColumn::make('published_at')->dateTime('Y-m-d')->sortable()->toggleable(),
                TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived',
                ]),
                SelectFilter::make('template_key')->label('Template')->options(PageTemplateRegistry::options()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('view')
                    ->label('View live')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record): string => url('/'.$record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => $record->status === 'published'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
