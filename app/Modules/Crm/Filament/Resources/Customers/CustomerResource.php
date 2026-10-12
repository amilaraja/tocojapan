<?php

namespace App\Modules\Crm\Filament\Resources\Customers;

use App\Models\Country;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Filament\Resources\Customers\Pages\EditCustomer;
use App\Modules\Crm\Filament\Resources\Customers\Pages\ListCustomers;
use App\Modules\Crm\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCustomer;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Admin → CRM → Customers (CRM-CUS-001..004). */
class CustomerResource extends Resource
{
    protected static ?string $model = CrmCustomer::class;

    protected static ?string $slug = 'crm/customers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /** Agents see the customers of Cases they can see. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->whereNull('merged_into_id');
        if (CrmAccess::isAdmin() || CrmAccess::isOperations()) {
            return $query;
        }

        return $query->whereIn('id', CrmAccess::scopeCases(CrmCase::query())->select('customer_id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->maxLength(190),
            TextInput::make('email')->email()->maxLength(190)->unique(ignoreRecord: true),
            TextInput::make('phone')->tel()->maxLength(60),
            TextInput::make('whatsapp')->label('WhatsApp')->tel()->maxLength(60),
            Select::make('country_id')->label('Country')->searchable()->options(fn () => Country::orderBy('name')->pluck('name', 'id')->all()),
            Textarea::make('notes')->rows(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('country')
                ->withCount(['cases', 'cases as open_cases_count' => fn ($q) => $q->whereIn('state', Crm::ACTIVE_STATES)->whereNull('merged_into_id'),
                    'cases as purchases_count' => fn ($q) => $q->where('type', Crm::TYPE_VEHICLE)->where('state', Crm::STATE_WON)])
                ->withMax('cases', 'last_activity_at'))
            ->defaultSort('cases_max_last_activity_at', 'desc')
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable()->placeholder('—')->description(fn (CrmCustomer $r) => $r->email),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->searchable()->placeholder('—'),
                TextColumn::make('country.name')->label('Country')->placeholder(fn (CrmCustomer $r) => $r->country_name ?: '—'),
                TextColumn::make('open_cases_count')->label('Open')->sortable(),
                TextColumn::make('purchases_count')->label('Purchases')->sortable()->badge()->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('cases_max_last_activity_at')->label('Last activity')->since()->sortable(),
                TextColumn::make('user_id')->label('Account')->formatStateUsing(fn ($state) => $state ? 'Yes' : '')->placeholder('Guest'),
            ])
            ->filters([
                TernaryFilter::make('returning')->label('Returning customers')
                    ->queries(true: fn (Builder $query) => $query->whereHas('cases', fn ($c) => $c->where('state', Crm::STATE_WON)), false: fn (Builder $query) => $query),
            ])
            ->recordUrl(fn (CrmCustomer $record) => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
