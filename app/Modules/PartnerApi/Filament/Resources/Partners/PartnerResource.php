<?php

namespace App\Modules\PartnerApi\Filament\Resources\Partners;

use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Stock\PartnerPricing;
use App\Modules\PartnerApi\Filament\Pages\PartnerUsage;
use App\Modules\PartnerApi\Filament\Resources\Partners\Pages\CreatePartner;
use App\Modules\PartnerApi\Filament\Resources\Partners\Pages\EditPartner;
use App\Modules\PartnerApi\Filament\Resources\Partners\Pages\ListPartners;
use App\Modules\PartnerApi\Filament\Resources\Partners\RelationManagers\ClientsRelationManager;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Support\PartnerAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Admin → Partner Network → Partners (UI brief section 2). */
class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Partner Network';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return PartnerAccess::canAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Partner settings')->columnSpanFull()->persistTabInQueryString()->tabs(
                collect(PartnerForm::sections())
                    ->map(fn (array $components, string $label) => Tab::make($label)->schema($components))
                    ->values()->all()
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['clients']))
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Partner')->searchable()->sortable()->weight('bold')
                    ->description(fn (Partner $record) => $record->country),
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state) => $state === Partner::TYPE_SELLING ? 'Selling' : 'Listing')
                    ->color(fn (string $state) => $state === Partner::TYPE_SELLING ? 'info' : 'gray'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        Partner::STATUS_ACTIVE => 'success',
                        Partner::STATUS_SUSPENDED => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('stock_scope')->label('Stock scope')->badge()->color('gray')
                    ->state(fn (Partner $record) => self::scopeSummary($record)),
                TextColumn::make('price_mode')->label('Price display')->badge()->color('gray')
                    ->state(fn (Partner $record) => PartnerPricing::label($record)),
                TextColumn::make('active_holds')->label('Active holds')
                    ->state(fn (Partner $record) => $record->isSelling()
                        ? VehicleHold::active()->where('partner_id', $record->id)->count().' of '.$record->max_active_holds
                        : '-'),
                TextColumn::make('last_activity_at')->label('Last activity')->since()->sortable()->placeholder('Never'),
                TextColumn::make('contract_ends_on')->label('Contract ends')->date('j M Y')->sortable()->placeholder('Open-ended')
                    ->description(fn (Partner $record) => $record->contract_ends_on?->isPast() ? 'expired' : null)
                    ->color(fn (Partner $record) => match (true) {
                        $record->contract_ends_on === null => null,
                        $record->contract_ends_on->isPast() => 'danger',
                        $record->contract_ends_on->lte(now()->addDays(30)) => 'warning',
                        default => null,
                    }),
            ])
            ->filters([
                SelectFilter::make('type')->options([Partner::TYPE_LISTING => 'Listing', Partner::TYPE_SELLING => 'Selling']),
                SelectFilter::make('status')
                    ->options([Partner::STATUS_ACTIVE => 'Active', Partner::STATUS_SUSPENDED => 'Suspended', Partner::STATUS_REVOKED => 'Revoked'])
                    ->placeholder('Active and suspended')
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->where('status', $data['value'])
                        : $query->where('status', '!=', Partner::STATUS_REVOKED)),
                SelectFilter::make('price_mode')->label('Price display')->options([
                    PartnerPricing::AS_IS => 'Toco price', PartnerPricing::PERCENT => 'Percentage markup',
                    PartnerPricing::FIXED => 'Fixed markup', PartnerPricing::ON_REQUEST => 'Price on request',
                ]),
                SelectFilter::make('contract_ending')->label('Contract ending')
                    ->options([30 => 'Within 30 days', 60 => 'Within 60 days', 90 => 'Within 90 days'])
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereNotNull('contract_ends_on')->whereBetween('contract_ends_on', [today(), today()->addDays((int) $data['value'])])
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('usage')->label('View usage')->icon('heroicon-o-chart-bar')->color('gray')
                    ->url(fn (Partner $record) => PartnerUsage::getUrl(['partner' => $record->id])),
                self::suspendAction(),
                self::reactivateAction(),
                self::revokeAction(),
            ]);
    }

    public static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')->icon('heroicon-o-pause-circle')->color('warning')
            ->visible(fn (Partner $record) => $record->status === Partner::STATUS_ACTIVE)
            ->modalHeading(fn (Partner $record) => 'Suspend '.$record->name.'?')
            ->modalDescription("The partner's access stops within a minute. You can reactivate any time.")
            ->schema([TextInput::make('reason')->label('Reason')->required()->maxLength(190)
                ->helperText('Kept in the audit log, for example "Unpaid invoice".')])
            ->action(function (Partner $record, array $data) {
                $record->suspend($data['reason']);
                Notification::make()->title($record->name.' suspended.')->success()->send();
            });
    }

    public static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label('Reactivate')->icon('heroicon-o-play-circle')->color('success')
            ->visible(fn (Partner $record) => $record->status === Partner::STATUS_SUSPENDED)
            ->requiresConfirmation()
            ->modalHeading(fn (Partner $record) => 'Reactivate '.$record->name.'?')
            ->action(function (Partner $record) {
                $record->reactivate();
                Notification::make()->title($record->name.' is active again.')->success()->send();
            });
    }

    public static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label('Revoke')->icon('heroicon-o-no-symbol')->color('danger')
            ->visible(fn (Partner $record) => $record->status !== Partner::STATUS_REVOKED)
            ->modalHeading(fn (Partner $record) => 'Revoke '.$record->name.'?')
            ->modalDescription('Permanent. All keys stop working and their photo links die. This cannot be undone.')
            ->modalSubmitActionLabel('Revoke permanently')
            ->schema(fn (Partner $record) => [
                TextInput::make('confirm_name')->label('Type the partner name to confirm')->required()
                    ->in([$record->name])->validationMessages(['in' => 'Type the name exactly: '.$record->name]),
                TextInput::make('reason')->label('Reason')->required()->maxLength(190),
            ])
            ->action(function (Partner $record, array $data) {
                $record->revoke($data['reason']);
                Notification::make()->title($record->name.' revoked.')->success()->send();
            });
    }

    public static function scopeSummary(Partner $record): string
    {
        $scope = array_filter((array) $record->stock_scope);
        if ($scope === []) {
            return 'All own stock';
        }

        $parts = [];
        if (! empty($scope['makes'])) {
            $parts[] = \App\Models\Make::whereIn('id', $scope['makes'])->orderBy('name')->pluck('name')->implode(', ');
        }
        if (! empty($scope['body_types'])) {
            $parts[] = \App\Models\BodyType::whereIn('id', $scope['body_types'])->orderBy('name')->pluck('name')->implode(', ');
        }
        if (! empty($scope['year_min']) || ! empty($scope['year_max'])) {
            $parts[] = ($scope['year_min'] ?? '…').' to '.($scope['year_max'] ?? '…');
        }
        if (! empty($scope['price_max'])) {
            $parts[] = 'under USD '.number_format((float) $scope['price_max']);
        }
        if (! empty($scope['price_min'])) {
            $parts[] = 'from USD '.number_format((float) $scope['price_min']);
        }

        return implode(', ', array_filter($parts));
    }

    public static function getRelations(): array
    {
        return [ClientsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
            'create' => CreatePartner::route('/create'),
            'edit' => EditPartner::route('/{record}/edit'),
        ];
    }
}
