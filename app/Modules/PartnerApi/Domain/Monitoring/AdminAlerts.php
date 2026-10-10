<?php

namespace App\Modules\PartnerApi\Domain\Monitoring;

use App\Models\User;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Filament\Pages\PartnerUsage;
use App\Modules\PartnerApi\Filament\Resources\Holds\VehicleHoldResource;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Support\PartnerAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Links name the admin panel explicitly: alerts are raised from the
 * scheduler too, where no panel is current.
 *
 * Notification-bell messages for partner admins (UI brief 6.1). Each alert
 * that could repeat is sent once per window, using a cache key.
 */
class AdminAlerts
{
    /** @return Collection<int, User> */
    public static function recipients(): Collection
    {
        try {
            return User::permission(PartnerAccess::ADMIN)->get();
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return new Collection; // permission not seeded yet: nobody to tell
        }
    }

    public static function holdPlaced(VehicleHold $hold): void
    {
        self::send(
            'Car on hold: '.$hold->vehicle?->stock_no,
            $hold->holderLabel().' for '.$hold->buyer_ref.'.',
            VehicleHoldResource::getUrl('index', panel: 'admin'),
            'heroicon-o-lock-closed',
        );
    }

    public static function holdExpiring(VehicleHold $hold): void
    {
        if (! Cache::add('partner-api:alert:expiring:'.$hold->id, 1, now()->addDays(4))) {
            return;
        }
        self::send(
            'Hold ending within 6 hours: '.$hold->vehicle?->stock_no,
            'Held by '.$hold->holderLabel().' for '.$hold->buyer_ref.'. Extend it or let it lapse.',
            VehicleHoldResource::getUrl('index', panel: 'admin'),
            'heroicon-o-clock',
            'warning',
        );
    }

    /** @param  Collection<int, VehicleHold>  $holds */
    public static function holdsExpiredUnused(Collection $holds): void
    {
        if ($holds->isEmpty()) {
            return;
        }
        self::send(
            $holds->count().' hold(s) ran out yesterday without a sale',
            $holds->map(fn (VehicleHold $h) => $h->vehicle?->stock_no.' ('.$h->holderLabel().')')->implode(', ').'. The cars are back on sale.',
            VehicleHoldResource::getUrl('index', ['tab' => 'recent'], panel: 'admin'),
            'heroicon-o-arrow-uturn-left',
        );
    }

    public static function quotaNearlyUsed(Partner $partner, string $kind, int $used, int $limit): void
    {
        if (! Cache::add('partner-api:alert:quota:'.$kind.':'.$partner->id.':'.now(config('partner_api.timezone'))->format('Ymd'), 1, now()->addDay())) {
            return;
        }
        self::send(
            $partner->name.' has used 80% of today\'s '.($kind === 'image' ? 'photo downloads' : 'requests'),
            number_format($used).' of '.number_format($limit).'. The allowance resets at midnight Japan time.',
            PartnerUsage::getUrl(['partner' => $partner->id], panel: 'admin'),
            'heroicon-o-chart-bar',
            'warning',
        );
    }

    public static function webhooksFailing(Partner $partner, ?string $error): void
    {
        if (! Cache::add('partner-api:alert:webhooks:'.$partner->id, 1, now()->addHours(6))) {
            return;
        }
        self::send(
            'Updates to '.$partner->name.' have been failing for over an hour',
            'Their site is not accepting change notifications. Last error: '.($error ?: 'unknown').'. Their scheduled sync still works.',
            PartnerUsage::getUrl(['partner' => $partner->id], panel: 'admin'),
            'heroicon-o-exclamation-triangle',
            'danger',
        );
    }

    public static function contractEnding(Partner $partner): void
    {
        self::send(
            $partner->name.'\'s contract ends in 14 days',
            'On '.$partner->contract_ends_on?->format('j M Y').' their keys stop working. Renew the end date to keep them connected.',
            \App\Modules\PartnerApi\Filament\Resources\Partners\PartnerResource::getUrl('edit', ['record' => $partner], panel: 'admin'),
            'heroicon-o-calendar',
            'warning',
        );
    }

    public static function anomaly(Partner $partner, string $what): void
    {
        if (! Cache::add('partner-api:alert:anomaly:'.$partner->id.':'.md5($what), 1, now()->addHours(12))) {
            return;
        }
        self::send(
            'Unusual activity from '.$partner->name,
            $what.' Check the usage page; suspend the partner if this is not expected.',
            PartnerUsage::getUrl(['partner' => $partner->id], panel: 'admin'),
            'heroicon-o-shield-exclamation',
            'danger',
        );
    }

    private static function send(string $title, string $body, string $url, string $icon, string $status = 'info'): void
    {
        $users = self::recipients();
        if ($users->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->status($status)
            ->actions([Action::make('open')->label('Open')->url($url)->markAsRead()])
            ->sendToDatabase($users);
    }
}
