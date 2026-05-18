<?php

namespace App\Filament\Admin\Resources\Makes;

use App\Filament\Admin\Resources\Makes\Pages\CreateMake;
use App\Filament\Admin\Resources\Makes\Pages\EditMake;
use App\Filament\Admin\Resources\Makes\Pages\ListMakes;
use App\Filament\Admin\Resources\Makes\Schemas\MakeForm;
use App\Filament\Admin\Resources\Makes\Tables\MakesTable;
use App\Models\Make;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MakeResource extends Resource
{
    protected static ?string $model = Make::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return MakeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MakesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMakes::route('/'),
            'create' => CreateMake::route('/create'),
            'edit' => EditMake::route('/{record}/edit'),
        ];
    }
}
