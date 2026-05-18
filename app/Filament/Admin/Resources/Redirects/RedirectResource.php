<?php

namespace App\Filament\Admin\Resources\Redirects;

use App\Filament\Admin\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Admin\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Admin\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Admin\Resources\Redirects\Schemas\RedirectForm;
use App\Filament\Admin\Resources\Redirects\Tables\RedirectsTable;
use App\Models\Redirect;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 52;


    protected static ?string $navigationLabel = 'Redirects';


    protected static ?string $recordTitleAttribute = 'from_path';

    public static function form(Schema $schema): Schema
    {
        return RedirectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RedirectsTable::configure($table);
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
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
