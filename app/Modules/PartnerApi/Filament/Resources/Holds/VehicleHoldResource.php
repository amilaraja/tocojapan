<?php

namespace App\Modules\PartnerApi\Filament\Resources\Holds;

use App\Filament\Admin\Resources\Vehicles\HoldActions;
use App\Filament\Admin\Resources\Vehicles\VehicleResource;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Filament\Resources\Holds\Pages\ListHolds;
use App\Modules\PartnerApi\Support\PartnerAccess;
use App\Services\Holds\HoldService;
use App\Services\Holds\VehicleUnavailable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Admin → Partner Network → Holds (UI brief section 3): every car on hold,
 * whoever holds it, with a live countdown. Read-only list plus actions;
 * holds are created by the channels themselves or "Hold for buyer".
 */
class VehicleHoldResource extends Resource
{
    protected static ?string $model = VehicleHold::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|\UnitEnum|null $navigationGroup = 'Partner Network';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Holds';

    protected static ?string $slug = 'holds';

    protected static ?string $modelLabel = 'hold';

    public static function canAccess(): bool
    {
        return PartnerAccess::canAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $active = VehicleHold::active()->count();

        return $active > 0 ? (string) $active : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['vehicle.media', 'partner', 'referralPartner', 'creator']))
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->emptyStateHeading('No cars are on hold right now.')
            ->emptyStateDescription('Holds appear here the moment a buyer anywhere starts paying for a car.')
            ->columns([
                ImageColumn::make('photo')->label('')->state(fn (VehicleHold $record) => $record->vehicle?->cardPhotoUrl())->square()->imageSize(48),
                TextColumn::make('vehicle.stock_no')->label('Vehicle')->weight('bold')->searchable()
                    ->description(fn (VehicleHold $record) => $record->vehicle?->title)
                    ->url(fn (VehicleHold $record) => $record->vehicle ? VehicleResource::getUrl('edit', ['record' => $record->vehicle]) : null),
                TextColumn::make('channel')->label('Held by')
                    ->state(fn (VehicleHold $record) => $record->holderLabel())
                    ->icon(fn (VehicleHold $record) => match (true) {
                        $record->channel === VehicleHold::CHANNEL_PARTNER => 'heroicon-o-globe-alt',
                        $record->channel === VehicleHold::CHANNEL_ADMIN => 'heroicon-o-user',
                        default => 'heroicon-o-computer-desktop',
                    }),
                TextColumn::make('buyer_ref')->label('Buyer ref')->searchable()->wrap(),
                TextColumn::make('price_snapshot')->label('Price locked')->money(fn (VehicleHold $record) => $record->currency ?: 'USD'),
                TextColumn::make('created_at')->label('Placed')->since()
                    ->description(fn (VehicleHold $record) => $record->created_at->copy()->timezone('Asia/Tokyo')->format('j M H:i')),
                TextColumn::make('expires_at')->label('Expires')
                    ->state(fn (VehicleHold $record) => self::countdown($record))
                    ->color(fn (VehicleHold $record) => self::countdownColor($record))
                    ->weight('bold')
                    ->description(fn (VehicleHold $record) => $record->isActive() && $record->expires_at
                        ? $record->expires_at->copy()->timezone('Asia/Tokyo')->format('j M H:i')
                        : null),
            ])
            ->recordActions([
                Action::make('extend')
                    ->label('Extend')->icon('heroicon-o-clock')->color('warning')
                    ->visible(fn (VehicleHold $record) => $record->isActive() && $record->expires_at !== null)
                    ->schema([
                        Select::make('hours')->label('Add')->required()->default(24)
                            ->options(collect(config('holds.extend_options'))->mapWithKeys(fn ($h) => [$h => "{$h} hours"])->all()),
                        TextInput::make('reason')->label('Reason (optional)')->maxLength(150),
                    ])
                    ->action(function (VehicleHold $record, array $data) {
                        app(HoldService::class)->extend($record, (int) $data['hours'], $data['reason'] ?: null);
                        Notification::make()->title('Hold extended.')->success()->send();
                    }),
                Action::make('release')
                    ->label('Release')->icon('heroicon-o-lock-open')->color('gray')
                    ->visible(fn (VehicleHold $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Release this hold?')
                    ->modalDescription('The car returns to sale everywhere within 2 minutes.')
                    ->schema([TextInput::make('reason')->label('Reason (optional)')->maxLength(150)])
                    ->action(function (VehicleHold $record, array $data) {
                        app(HoldService::class)->release($record, 'Released by '.auth()->user()?->name.($data['reason'] ? ': '.$data['reason'] : ''));
                        Notification::make()->title('Hold released. The car is back on sale.')->success()->send();
                    }),
                Action::make('markSold')
                    ->label('Mark sold')->icon('heroicon-o-banknotes')->color('danger')
                    ->visible(fn (VehicleHold $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Confirm the sale?')
                    ->modalDescription(fn (VehicleHold $record) => $record->vehicle ? HoldActions::holdSummary($record->vehicle).' This marks the car sold to that buyer.' : '')
                    ->action(function (VehicleHold $record) {
                        try {
                            app(HoldService::class)->confirmSale($record, auth()->user());
                            Notification::make()->title('Sale confirmed. The car is marked sold everywhere.')->success()->send();
                        } catch (VehicleUnavailable $e) {
                            Notification::make()->title('Could not confirm the sale')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function countdown(VehicleHold $record): string
    {
        return match (true) {
            ! $record->isActive() => ucfirst($record->state),
            $record->expires_at === null => 'Paid, awaiting confirmation',
            default => HoldActions::remaining($record->expires_at),
        };
    }

    public static function countdownColor(VehicleHold $record): ?string
    {
        if (! $record->isActive() || $record->expires_at === null) {
            return null;
        }
        $minutes = now()->diffInMinutes($record->expires_at, false);

        return match (true) {
            $minutes < 60 => 'danger',
            $minutes < 360 => 'warning',
            default => null,
        };
    }

    public static function getPages(): array
    {
        return ['index' => ListHolds::route('/')];
    }
}
