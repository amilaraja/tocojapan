<?php

namespace App\Filament\Admin\Resources\SparePartInquiries;

use App\Filament\Admin\Resources\SparePartInquiries\Pages\CreateSparePartInquiry;
use App\Filament\Admin\Resources\SparePartInquiries\Pages\EditSparePartInquiry;
use App\Filament\Admin\Resources\SparePartInquiries\Pages\ListSparePartInquiries;
use App\Filament\Admin\Resources\SparePartInquiries\Schemas\SparePartInquiryForm;
use App\Filament\Admin\Resources\SparePartInquiries\Tables\SparePartInquiriesTable;
use App\Models\SparePartInquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SparePartInquiryResource extends Resource
{
    protected static ?string $model = SparePartInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Spare-part inquiries';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::where('is_handled', false)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return SparePartInquiryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SparePartInquiriesTable::configure($table);
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
            'index' => ListSparePartInquiries::route('/'),
            'create' => CreateSparePartInquiry::route('/create'),
            'edit' => EditSparePartInquiry::route('/{record}/edit'),
        ];
    }
}
