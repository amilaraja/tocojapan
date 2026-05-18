<?php

namespace App\Filament\Admin\Resources\Vehicles;

use App\Filament\Admin\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Admin\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Admin\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Admin\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Admin\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['ref_no', 'title', 'slug'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Ref' => $record->ref_no,
            'Status' => ucfirst((string) $record->status),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
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
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
