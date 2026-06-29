<?php

namespace App\Filament\Admin\Resources\VehicleOptions;

use App\Filament\Admin\Resources\VehicleOptions\Pages\CreateVehicleOption;
use App\Filament\Admin\Resources\VehicleOptions\Pages\EditVehicleOption;
use App\Filament\Admin\Resources\VehicleOptions\Pages\ListVehicleOptions;
use App\Filament\Admin\Resources\VehicleOptions\Schemas\VehicleOptionForm;
use App\Filament\Admin\Resources\VehicleOptions\Tables\VehicleOptionsTable;
use App\Models\VehicleOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VehicleOptionResource extends Resource
{
    protected static ?string $model = VehicleOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Vehicle options';

    protected static ?string $modelLabel = 'Vehicle option';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return VehicleOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleOptionsTable::configure($table);
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
            'index' => ListVehicleOptions::route('/'),
            'create' => CreateVehicleOption::route('/create'),
            'edit' => EditVehicleOption::route('/{record}/edit'),
        ];
    }
}
