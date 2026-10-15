<?php

namespace App\Modules\Crm\Filament\Resources\Queues;

use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Models\CrmQueue;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Admin → CRM → Queues: who receives which Cases by rotation (CRM-ASN-001..003). */
class QueueResource extends Resource
{
    protected static ?string $model = CrmQueue::class;

    protected static ?string $slug = 'queues';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Queues';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return CrmAccess::isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('max_open_cases')->label('Most open Cases per person')->numeric()->minValue(1)->default(30)->required()
                ->helperText('Someone at this number gets no new Cases until one closes.'),
            CheckboxList::make('case_types')->label('Case types')->options(Crm::TYPES)->helperText('None ticked = every type.'),
            CheckboxList::make('sources')->options(Crm::SOURCES)->columns(2)->helperText('None ticked = every source.'),
            Select::make('users')->label('Members')->multiple()->preload()->columnSpanFull()
                ->relationship('users', 'name', fn ($query) => $query->whereIn('users.id', CrmAccess::staff()->pluck('id')))
                ->helperText('New Cases go to members in turn, skipping anyone away or at the limit.'),
            Toggle::make('is_active')->default(true),
            TextInput::make('sort')->numeric()->default(0)->helperText('The first matching queue wins.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('case_types')->label('Types')->badge()->formatStateUsing(fn ($state) => Crm::TYPES[$state] ?? $state)->placeholder('All'),
                TextColumn::make('users.name')->label('Members')->badge()->color('gray')->placeholder('Nobody yet'),
                TextColumn::make('max_open_cases')->label('Limit'),
                TextColumn::make('is_active')->label('Active')->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQueues::route('/'),
            'create' => Pages\CreateQueue::route('/create'),
            'edit' => Pages\EditQueue::route('/{record}/edit'),
        ];
    }
}
