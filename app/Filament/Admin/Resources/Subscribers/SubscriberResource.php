<?php

namespace App\Filament\Admin\Resources\Subscribers;

use App\Filament\Admin\Resources\Subscribers\Pages\CreateSubscriber;
use App\Filament\Admin\Resources\Subscribers\Pages\EditSubscriber;
use App\Filament\Admin\Resources\Subscribers\Pages\ListSubscribers;
use App\Filament\Admin\Resources\Subscribers\Schemas\SubscriberForm;
use App\Filament\Admin\Resources\Subscribers\Tables\SubscribersTable;
use App\Models\Subscriber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Newsletter subscribers';

    protected static ?string $modelLabel = 'Subscriber';

    protected static string|\UnitEnum|null $navigationGroup = 'Enquiries';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return SubscriberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscribersTable::configure($table);
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
            'index' => ListSubscribers::route('/'),
            'create' => CreateSubscriber::route('/create'),
            'edit' => EditSubscriber::route('/{record}/edit'),
        ];
    }
}
