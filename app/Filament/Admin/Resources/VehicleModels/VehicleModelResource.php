<?php

namespace App\Filament\Admin\Resources\VehicleModels;

use App\Filament\Admin\Resources\VehicleModels\Pages\CreateVehicleModel;
use App\Filament\Admin\Resources\VehicleModels\Pages\EditVehicleModel;
use App\Filament\Admin\Resources\VehicleModels\Pages\ListVehicleModels;
use App\Filament\Admin\Resources\VehicleModels\Schemas\VehicleModelForm;
use App\Filament\Admin\Resources\VehicleModels\Tables\VehicleModelsTable;
use App\Models\VehicleModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VehicleModelResource extends Resource
{
    protected static ?string $model = VehicleModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return VehicleModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleModelsTable::configure($table);
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
            'index' => ListVehicleModels::route('/'),
            'create' => CreateVehicleModel::route('/create'),
            'edit' => EditVehicleModel::route('/{record}/edit'),
        ];
    }
}
