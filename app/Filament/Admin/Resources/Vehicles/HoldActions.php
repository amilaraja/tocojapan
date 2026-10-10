<?php

namespace App\Filament\Admin\Resources\Vehicles;

use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Models\Partner;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use App\Services\Holds\VehicleUnavailable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Staff hold controls on a vehicle (UI brief TOC-PAPI-UI-01 section 5,
 * Amendment 01): hold for a buyer (e.g. while a JWT Japan buyer negotiates),
 * extend, release, and a hold-aware "Mark as sold". Shared by the vehicle
 * table and the edit page.
 */
class HoldActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return array_map(
            // The edit page keeps the record in memory: reload it so the status
            // field and the other hold buttons match what just happened.
            fn (Action $action) => $action->after(function (Vehicle $record, $livewire): void {
                $record->refresh();
                if (method_exists($livewire, 'refreshFormData')) {
                    $livewire->refreshFormData(['status']);
                }
            }),
            [self::holdForBuyer(), self::extend(), self::release(), self::markSold()],
        );
    }

    public static function holdForBuyer(): Action
    {
        return Action::make('holdForBuyer')
            ->label('Hold for buyer')
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->visible(fn (Vehicle $record) => $record->status === 'published')
            ->modalHeading('Hold this car for a buyer')
            ->modalDescription('The car shows as "Under offer" on tocojapan.com and every partner site, and nobody else can buy it until the hold ends.')
            ->schema([
                Select::make('source')
                    ->label('Where the buyer came from')
                    ->options(self::sourceOptions())
                    ->default('direct')
                    ->required()
                    ->helperText('Pick the partner when their site sent the buyer (for example JWT Japan), so they are credited.'),
                TextInput::make('buyer_ref')
                    ->label('Buyer reference')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Name or inquiry number, for example "JWT inquiry 55821, M. Banda".'),
                Select::make('hours')
                    ->label('Hold for')
                    ->options([24 => '24 hours', 48 => '48 hours', 72 => '72 hours', 96 => '96 hours'])
                    ->default((int) config('holds.hours.admin', 48))
                    ->required()
                    ->helperText('The car returns to sale automatically when the time runs out. You can extend it.'),
            ])
            ->action(function (Vehicle $record, array $data) {
                try {
                    app(HoldService::class)->claim($record, new HoldClaim(
                        channel: VehicleHold::CHANNEL_ADMIN,
                        buyerRef: $data['buyer_ref'],
                        hours: (int) $data['hours'],
                        actor: auth()->user(),
                        referralPartner: self::partnerFromSource($data['source']),
                    ));
                    Notification::make()->title('Car on hold for '.$data['buyer_ref'].'.')->success()->send();
                } catch (VehicleUnavailable $e) {
                    Notification::make()->title('Could not hold this car')->body(self::unavailableText($e))->danger()->send();
                }
            });
    }

    public static function extend(): Action
    {
        return Action::make('extendHold')
            ->label('Extend hold')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->visible(fn (Vehicle $record) => $record->activeHold?->expires_at !== null)
            ->modalHeading('Extend the hold')
            ->modalDescription(fn (Vehicle $record) => self::holdSummary($record))
            ->schema([
                Select::make('hours')->label('Add')
                    ->options(collect(config('holds.extend_options'))->mapWithKeys(fn ($h) => [$h => "{$h} hours"])->all())
                    ->default(24)->required(),
                TextInput::make('reason')->label('Reason (optional)')->maxLength(150),
            ])
            ->action(function (Vehicle $record, array $data) {
                $hold = app(HoldService::class)->extend($record->activeHold, (int) $data['hours'], $data['reason'] ?: null);
                Notification::make()->title('Hold extended to '.self::time($hold->expires_at).'.')->success()->send();
            });
    }

    public static function release(): Action
    {
        return Action::make('releaseHold')
            ->label('Release hold')
            ->icon('heroicon-o-lock-open')
            ->color('gray')
            ->visible(fn (Vehicle $record) => $record->activeHold !== null)
            ->requiresConfirmation()
            ->modalHeading('Release the hold?')
            ->modalDescription(fn (Vehicle $record) => self::holdSummary($record).' The car returns to sale everywhere within 2 minutes.')
            ->schema([TextInput::make('reason')->label('Reason (optional)')->maxLength(150)])
            ->action(function (Vehicle $record, array $data) {
                app(HoldService::class)->release($record->activeHold, 'Released by '.auth()->user()?->name.($data['reason'] ? ': '.$data['reason'] : ''));
                Notification::make()->title('Hold released. The car is back on sale.')->success()->send();
            });
    }

    /**
     * Hold-aware sale confirmation (FR-HLD-05). A held car is sold to its
     * holder; selling to someone else needs the hold released first.
     */
    public static function markSold(): Action
    {
        return Action::make('markSold')
            ->label('Mark as sold')
            ->icon('heroicon-o-banknotes')
            ->color('danger')
            ->visible(fn (Vehicle $record) => in_array($record->status, ['published', 'reserved'], true))
            ->modalHeading('Mark this vehicle as sold?')
            ->modalDescription(fn (Vehicle $record) => $record->activeHold
                ? self::holdSummary($record).' This confirms the sale to that buyer. If the car went to someone else, release the hold first.'
                : 'It shows a SOLD badge on the public site for 90 days, is removed from partner sites, and buy buttons disappear at once.')
            ->schema(fn (Vehicle $record) => $record->activeHold ? [] : [
                Select::make('source')
                    ->label('Sold through')
                    ->options(['website' => 'tocojapan.com'] + self::sourceOptions())
                    ->required()
                    ->helperText('Pick a partner when their site sent the buyer, so they are credited.'),
                TextInput::make('buyer_ref')->label('Buyer reference')->required()->maxLength(120),
            ])
            ->action(function (Vehicle $record, array $data) {
                $holds = app(HoldService::class);
                try {
                    if ($hold = $record->activeHold) {
                        $holds->confirmSale($hold, auth()->user());
                    } else {
                        $source = $data['source'];
                        $holds->sellUnheld($record, new HoldClaim(
                            channel: VehicleHold::CHANNEL_ADMIN,
                            buyerRef: ($source === 'website' ? 'tocojapan.com: ' : ($source === 'direct' ? 'Direct: ' : '')).$data['buyer_ref'],
                            actor: auth()->user(),
                            referralPartner: self::partnerFromSource($source),
                        ));
                    }
                    Notification::make()->title('Marked as sold.')->success()->send();
                } catch (VehicleUnavailable $e) {
                    Notification::make()->title('Could not mark as sold')->body(self::unavailableText($e))->danger()->send();
                }
            });
    }

    /** "Held by Admin, for JWT Japan for buyer X, 41 h left (until 12 Oct 14:00)." */
    public static function holdSummary(Vehicle $record): string
    {
        $hold = $record->activeHold;
        if (! $hold) {
            return '';
        }

        $until = $hold->expires_at
            ? self::remaining($hold->expires_at).' left (until '.self::time($hold->expires_at).')'
            : 'paid, waiting for you to confirm the sale';

        return 'Held by '.$hold->holderLabel().' for '.$hold->buyer_ref.', '.$until.'.';
    }

    public static function remaining(Carbon $at): string
    {
        $minutes = max(0, (int) now()->diffInMinutes($at, false));

        return intdiv($minutes, 60).' h '.($minutes % 60).' m';
    }

    private static function time(?Carbon $at): string
    {
        return $at ? $at->copy()->timezone('Asia/Tokyo')->format('j M H:i') : '';
    }

    /** @return array<string, string> */
    private static function sourceOptions(): array
    {
        return ['direct' => 'Walk-in or direct']
            + Partner::query()->where('status', Partner::STATUS_ACTIVE)->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => ['partner:'.$id => $name.' (partner)'])->all();
    }

    private static function partnerFromSource(?string $source): ?Partner
    {
        return $source && str_starts_with($source, 'partner:') ? Partner::find((int) substr($source, 8)) : null;
    }

    private static function unavailableText(VehicleUnavailable $e): string
    {
        return $e->currentHold
            ? 'Held by '.$e->currentHold->holderLabel().' until '.self::time($e->currentHold->expires_at).'. Release the hold first if this sale is real.'
            : $e->getMessage();
    }
}
