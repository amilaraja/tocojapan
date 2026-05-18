<?php

namespace App\Filament\Admin\Resources\NotFoundLogs;

use App\Filament\Admin\Resources\NotFoundLogs\Pages\ListNotFoundLogs;
use App\Filament\Admin\Resources\NotFoundLogs\Tables\NotFoundLogsTable;
use App\Models\NotFoundLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NotFoundLogResource extends Resource
{
    protected static ?string $model = NotFoundLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = '404 Log';

    protected static ?int $navigationSort = 81;

    protected static ?string $recordTitleAttribute = 'path';

    public static function table(Table $table): Table
    {
        return NotFoundLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotFoundLogs::route('/'),
        ];
    }
}
