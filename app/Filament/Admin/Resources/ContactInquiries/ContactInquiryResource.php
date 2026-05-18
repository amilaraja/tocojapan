<?php

namespace App\Filament\Admin\Resources\ContactInquiries;

use App\Filament\Admin\Resources\ContactInquiries\Pages\CreateContactInquiry;
use App\Filament\Admin\Resources\ContactInquiries\Pages\EditContactInquiry;
use App\Filament\Admin\Resources\ContactInquiries\Pages\ListContactInquiries;
use App\Filament\Admin\Resources\ContactInquiries\Schemas\ContactInquiryForm;
use App\Filament\Admin\Resources\ContactInquiries\Tables\ContactInquiriesTable;
use App\Models\ContactInquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactInquiryResource extends Resource
{
    protected static ?string $model = ContactInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Enquiries';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Contact inquiries';

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
        return ContactInquiryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactInquiriesTable::configure($table);
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
            'index' => ListContactInquiries::route('/'),
            'create' => CreateContactInquiry::route('/create'),
            'edit' => EditContactInquiry::route('/{record}/edit'),
        ];
    }
}
