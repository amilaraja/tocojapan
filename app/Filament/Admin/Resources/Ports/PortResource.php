<?php

namespace App\Filament\Admin\Resources\Ports;

use App\Filament\Admin\Resources\Ports\Pages\CreatePort;
use App\Filament\Admin\Resources\Ports\Pages\EditPort;
use App\Filament\Admin\Resources\Ports\Pages\ListPorts;
use App\Filament\Admin\Resources\Ports\Schemas\PortForm;
use App\Filament\Admin\Resources\Ports\Tables\PortsTable;
use App\Models\Port;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PortResource extends Resource
{
    protected static ?string $model = Port::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PortForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortsTable::configure($table);
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
            'index' => ListPorts::route('/'),
            'create' => CreatePort::route('/create'),
            'edit' => EditPort::route('/{record}/edit'),
        ];
    }
}
