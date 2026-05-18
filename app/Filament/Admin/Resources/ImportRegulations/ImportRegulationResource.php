<?php

namespace App\Filament\Admin\Resources\ImportRegulations;

use App\Filament\Admin\Resources\ImportRegulations\Pages\CreateImportRegulation;
use App\Filament\Admin\Resources\ImportRegulations\Pages\EditImportRegulation;
use App\Filament\Admin\Resources\ImportRegulations\Pages\ListImportRegulations;
use App\Filament\Admin\Resources\ImportRegulations\Schemas\ImportRegulationForm;
use App\Filament\Admin\Resources\ImportRegulations\Tables\ImportRegulationsTable;
use App\Models\ImportRegulation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImportRegulationResource extends Resource
{
    protected static ?string $model = ImportRegulation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Import regulations';

    protected static ?string $recordTitleAttribute = 'year_restriction';

    public static function form(Schema $schema): Schema
    {
        return ImportRegulationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportRegulationsTable::configure($table);
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
            'index' => ListImportRegulations::route('/'),
            'create' => CreateImportRegulation::route('/create'),
            'edit' => EditImportRegulation::route('/{record}/edit'),
        ];
    }
}
