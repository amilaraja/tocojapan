<?php

namespace Tests\Crm\Support;

use App\Models\Country;
use App\Models\Order;
use App\Models\Port;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\Crm\Domain\Notifier;
use App\Modules\Crm\Models\CrmAgentStatus;
use App\Modules\Crm\Models\CrmQueue;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmSettings;
use Illuminate\Support\Facades\Cache;

/** Builders for CRM tests. */
class CrmData
{
    public static function reset(): void
    {
        Notifier::flushRules();
        Notifier::$silent = false;
        Cache::forget('crm:settings');
        // Every hour of every day is business time unless a test says otherwise.
        CrmSettings::set('calendar.hours', array_fill_keys(range(1, 7), ['00:00', '23:59']));
        \Illuminate\Support\Facades\DB::table('crm_holidays')->delete();
    }

    public static function staff(string $role = CrmAccess::ROLE_AGENT, array $attributes = [], ?string $queue = 'Sales', ?string $nickname = null): User
    {
        $user = User::factory()->create($attributes + ['email_verified_at' => now()]);
        $user->assignRole($role);
        if ($queue) {
            CrmQueue::where('name', $queue)->firstOrFail()->members()->create(['user_id' => $user->id]);
        }
        if ($nickname) {
            CrmAgentStatus::create(['user_id' => $user->id, 'nickname' => $nickname]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public static function admin(): User
    {
        return self::staff(CrmAccess::ROLE_ADMIN, queue: null);
    }

    public static function buyer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email_verified_at' => now()]);
    }

    public static function car(array $attributes = []): Vehicle
    {
        return Vehicle::factory()->create($attributes + ['status' => 'published', 'stock_no' => 'E0'.random_int(1000, 9999), 'price_fob' => 9000]);
    }

    public static function port(bool $inspection = false): Port
    {
        $country = Country::create(['iso2' => $inspection ? 'LK' : 'KE', 'name' => $inspection ? 'Sri Lanka' : 'Kenya', 'slug' => $inspection ? 'sri-lanka' : 'kenya', 'is_active' => true, 'pre_inspection_required' => $inspection]);

        return Port::create(['country_id' => $country->id, 'name' => $inspection ? 'Colombo' : 'Mombasa', 'slug' => $inspection ? 'colombo' : 'mombasa', 'is_active' => true, 'rate_per_m3' => 50, 'insurance_pct' => 1.5]);
    }

    public static function quote(User $buyer, Vehicle $car, array $attributes = []): Quote
    {
        return Quote::create($attributes + [
            'user_id' => $buyer->id, 'vehicle_id' => $car->id, 'contact_name' => $buyer->name, 'contact_email' => $buyer->email,
            'message' => 'Please quote CIF Mombasa.', 'status' => 'submitted',
        ]);
    }

    public static function order(User $buyer, Vehicle $car, array $attributes = []): Order
    {
        return Order::create($attributes + [
            'user_id' => $buyer->id, 'vehicle_id' => $car->id, 'amount_usd' => 9500, 'currency' => 'USD',
            'status' => 'pending', 'payment_provider' => 'paypal',
        ]);
    }
}
