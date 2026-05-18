<?php

namespace App\Filament\Admin\Resources\BodyTypes;

use App\Filament\Admin\Resources\BodyTypes\Pages\CreateBodyType;
use App\Filament\Admin\Resources\BodyTypes\Pages\EditBodyType;
use App\Filament\Admin\Resources\BodyTypes\Pages\ListBodyTypes;
use App\Filament\Admin\Resources\BodyTypes\Schemas\BodyTypeForm;
use App\Filament\Admin\Resources\BodyTypes\Tables\BodyTypesTable;
use App\Models\BodyType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BodyTypeResource extends Resource
{
    protected static ?string $model = BodyType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return BodyTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BodyTypesTable::configure($table);
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
            'index' => ListBodyTypes::route('/'),
            'create' => CreateBodyType::route('/create'),
            'edit' => EditBodyType::route('/{record}/edit'),
        ];
    }
}
