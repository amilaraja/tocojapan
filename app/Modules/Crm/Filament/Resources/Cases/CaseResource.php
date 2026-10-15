<?php

namespace App\Modules\Crm\Filament\Resources\Cases;

use App\Models\Country;
use App\Models\User;
use App\Modules\Crm\Domain\Assigner;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\SlaClock;
use App\Modules\Crm\Filament\Resources\Cases\Pages\CreateCase;
use App\Modules\Crm\Filament\Resources\Cases\Pages\ListCases;
use App\Modules\Crm\Filament\Resources\Cases\Pages\ViewCase;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmQueue;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmUi;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Admin → CRM → Cases (CRM-CAS-004, 008, SLA-005, ASN-005). */
class CaseResource extends Resource
{
    protected static ?string $model = CrmCase::class;

    protected static ?string $slug = 'cases';

    protected static ?string $modelLabel = 'Case';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    public static function canCreate(): bool
    {
        return CrmAccess::canUse();
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return CrmAccess::scopeCases(parent::getEloquentQuery()->whereNull('merged_into_id'));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = CrmAccess::scopeCases(CrmCase::query()->active())
            ->where(fn (Builder $q) => $q->whereNull('owner_id')->orWhere('owner_id', auth()->id()))
            ->whereHas('slaTimers', fn (Builder $t) => $t->whereNull('met_at')->whereNull('cancelled_at')->whereNotNull('breached_at'))
            ->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'vehicle', 'owner', 'slaTimers' => fn ($q) => $q->whereNull('met_at')->whereNull('cancelled_at')]))
            ->defaultSort('last_activity_at', 'desc')
            ->poll('60s')
            ->columns([
                IconColumn::make('sla')->label('SLA')->state(fn (CrmCase $record) => SlaClock::colour($record) ?? 'none')
                    ->icon(fn (string $state) => $state === 'none' ? 'heroicon-o-minus-small' : 'heroicon-s-clock')
                    ->color(fn (string $state) => $state === 'none' ? 'gray' : $state)
                    ->tooltip(fn (string $state) => match ($state) { 'danger' => 'Late', 'warning' => 'Due soon', 'success' => 'On time', default => 'No clock running' }),
                TextColumn::make('reference')->label('Case')->weight('bold')->sortable()
                    ->description(fn (CrmCase $record) => Crm::SOURCES[$record->source] ?? $record->source)
                    // One search box for reference, name, email, phone, stock no., chassis and B/L (CRM-CAS-008).
                    ->searchable(query: fn (Builder $query, string $search) => self::search($query, $search)),
                TextColumn::make('customer.name')->label('Customer')->placeholder('—')
                    ->description(fn (CrmCase $record) => $record->customer?->email),
                TextColumn::make('progress')->label('Stage / status')->badge()
                    ->state(fn (CrmCase $record) => $record->progressLabel())
                    ->color(fn (CrmCase $record) => $record->isVehicleSale() && $record->stage ? Color::hex(CrmUi::stageHex($record->stage)) : 'gray'),
                TextColumn::make('vehicle.stock_no')->label('Vehicle')->placeholder('—')
                    ->description(fn (CrmCase $record) => $record->vehicle ? mb_strimwidth(trim($record->vehicle->title), 0, 34, '…') : null),
                TextColumn::make('owner.name')->label('Owner')->placeholder('Unassigned')->sortable(),
                TextColumn::make('state')->badge()->formatStateUsing(fn (string $state) => Crm::STATES[$state] ?? $state)
                    ->color(fn (string $state) => CrmUi::stateColor($state)),
                TextColumn::make('last_customer_message_at')->label('Customer wrote')->since()->sortable()->placeholder('—')
                    ->color(fn (CrmCase $record) => $record->last_customer_message_at && (! $record->last_staff_response_at || $record->last_staff_response_at < $record->last_customer_message_at) ? (SlaClock::colour($record) ?? 'gray') : 'gray'),
                TextColumn::make('last_staff_response_at')->label('We replied')->since()->sortable()->placeholder('Not yet'),
                TextColumn::make('next_follow_up_at')->label('Follow up')->dateTime('j M H:i', CrmUi::tz())->sortable()->placeholder('—')
                    ->color(fn (CrmCase $record) => $record->isActive() && $record->next_follow_up_at?->isPast() ? 'danger' : null),
                TextColumn::make('value_usd')->label('Value')->money('USD', 0)->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Created')->dateTime('j M Y', CrmUi::tz())->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('state')->multiple()->options(Crm::STATES)->default(Crm::ACTIVE_STATES),
                SelectFilter::make('type')->options(Crm::TYPES),
                SelectFilter::make('stage')->options(collect(Crm::STAGES)->mapWithKeys(fn ($name, $n) => [$n => Crm::stageLabel($n)])->all()),
                SelectFilter::make('owner_id')->label('Owner')
                    ->options(fn () => ['none' => 'Unassigned'] + CrmUi::staffOptions())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'none' => $query->whereNull('owner_id'),
                        default => $query->where('owner_id', $data['value']),
                    }),
                SelectFilter::make('source')->options(Crm::SOURCES),
                SelectFilter::make('queue_id')->label('Queue')->options(fn () => CrmQueue::orderBy('sort')->pluck('name', 'id')->all()),
                SelectFilter::make('dest_country_id')->label('Country')->searchable()
                    ->options(fn () => Country::whereIn('id', CrmCase::query()->whereNotNull('dest_country_id')->distinct()->pluck('dest_country_id'))->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('supplier')->label('Dealer stock')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('vehicle', fn ($v) => $v->partnerStock()),
                        false: fn (Builder $query) => $query->whereDoesntHave('vehicle', fn ($v) => $v->partnerStock()),
                    ),
                TernaryFilter::make('late')->label('SLA breached')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('slaTimers', fn ($t) => $t->whereNull('met_at')->whereNull('cancelled_at')->whereNotNull('breached_at')),
                        false: fn (Builder $query) => $query->whereDoesntHave('slaTimers', fn ($t) => $t->whereNull('met_at')->whereNull('cancelled_at')->whereNotNull('breached_at')),
                    ),
                TernaryFilter::make('stale')->label('Stale')->nullable()->attribute('stale_at'),
                Filter::make('created')->schema([
                    DatePicker::make('from')->label('Created from'),
                    DatePicker::make('until')->label('Created until'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->filtersFormColumns(3)
            ->toolbarActions([
                BulkAction::make('reassign')->label('Assign to…')->icon('heroicon-o-user-plus')
                    ->visible(fn () => CrmAccess::isAdmin())
                    ->schema([
                        Select::make('owner')->label('New owner')->options(fn () => CrmUi::staffOptions())->searchable()->required(),
                        Textarea::make('note')->label('Note (optional)')->rows(2),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $to = User::find($data['owner']);
                        $records->each(fn (CrmCase $case) => Assigner::assign($case, $to, $data['note'] ?? null, auth()->user()));
                        Notification::make()->title($records->count().' Cases assigned to '.$to->name)->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->recordUrl(fn (CrmCase $record) => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No Cases here')
            ->emptyStateDescription('New enquiries from the website appear here within a minute.');
    }

    /** Reference, customer name / email / phone, stock no., chassis, B/L (CRM-CAS-008). */
    public static function search(Builder $query, string $search): Builder
    {
        $like = '%'.trim($search).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('reference', 'like', $like)
            ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
            ->orWhereHas('vehicle', fn (Builder $v) => $v->where('stock_no', 'like', $like)->orWhere('chassis_number', 'like', $like))
            ->orWhereHas('steps', fn (Builder $s) => $s->where('data', 'like', '%"bl_number":"%'.trim($search).'%'))
            ->orWhereHas('links', fn (Builder $l) => $l->whereHasMorph('linkable', [\App\Models\Order::class], fn (Builder $o) => $o->where('bl_number', 'like', $like)->orWhere('order_no', 'like', $like))));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCases::route('/'),
            'create' => CreateCase::route('/create'),
            'view' => ViewCase::route('/{record}'),
        ];
    }
}
