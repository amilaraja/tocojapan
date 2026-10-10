<?php

namespace App\Modules\PartnerApi\Domain\Stock;

use App\Models\Supplier;
use App\Models\Vehicle;
use App\Modules\PartnerApi\Models\Partner;
use Illuminate\Database\Eloquent\Builder;

/**
 * The partner's contracted slice of stock, built into the query itself
 * (SRS §8.2: never filtered afterwards). Phase 1: Toco's own stock only,
 * narrowed by the partner's make, body type, year and price settings.
 *
 * stock_scope JSON: {"makes":[ids], "body_types":[ids], "year_min":int,
 * "year_max":int, "price_min":num, "price_max":num}; null = all own stock.
 */
class PartnerScope
{
    /** Days a sold car stays visible so partners can see it went (then 404 / tombstone). */
    public const SOLD_VISIBLE_DAYS = 30;

    public const YEAR_SQL = 'COALESCE(vehicles.year_first_reg, vehicles.manufacture_year)';

    // CAST keeps the comparison numeric on SQLite as well as MariaDB.
    public const PRICE_SQL = 'CAST(CASE WHEN vehicles.price_fob_discount > 0 THEN vehicles.price_fob_discount ELSE vehicles.price_fob END AS DECIMAL(12,2))';

    /** Toco's own stock, any status, trashed excluded. @return Builder<Vehicle> */
    public static function ownStock(): Builder
    {
        return Vehicle::query()->where(function (Builder $query) {
            $query->whereNull('vehicles.supplier_id');
            if ($own = Supplier::ownStockId()) {
                $query->orWhere('vehicles.supplier_id', $own);
            }
        });
    }

    /** Own stock inside the partner's filters, any status, trashed excluded. @return Builder<Vehicle> */
    public static function base(Partner $partner): Builder
    {
        $query = self::ownStock();

        $scope = $partner->stock_scope ?? [];

        if (! empty($scope['makes'])) {
            $query->whereIn('vehicles.make_id', $scope['makes']);
        }
        if (! empty($scope['body_types'])) {
            $query->whereIn('vehicles.body_type_id', $scope['body_types']);
        }
        if (! empty($scope['year_min'])) {
            $query->whereRaw(self::YEAR_SQL.' >= ?', [(int) $scope['year_min']]);
        }
        if (! empty($scope['year_max'])) {
            $query->whereRaw(self::YEAR_SQL.' <= ?', [(int) $scope['year_max']]);
        }
        if (! empty($scope['price_min']) || ! empty($scope['price_max'])) {
            $query->where('vehicles.price_on_request', false);
            if (! empty($scope['price_min'])) {
                $query->whereRaw(self::PRICE_SQL.' >= ?', [(float) $scope['price_min']]);
            }
            if (! empty($scope['price_max'])) {
                $query->whereRaw(self::PRICE_SQL.' <= ?', [(float) $scope['price_max']]);
            }
        }

        return $query;
    }

    /** What a partner may see right now: on sale, under offer, or recently sold. @return Builder<Vehicle> */
    public static function visible(Partner $partner): Builder
    {
        return self::base($partner)->where(function (Builder $query) {
            $query->whereIn('vehicles.status', ['published', 'reserved'])
                ->orWhere(fn (Builder $query) => $query->where('vehicles.status', 'sold')
                    ->where('vehicles.sold_at', '>=', now()->subDays(self::SOLD_VISIBLE_DAYS)));
        });
    }

    /** For sale or under offer: the default list. @return Builder<Vehicle> */
    public static function listable(Partner $partner): Builder
    {
        return self::base($partner)->whereIn('vehicles.status', ['published', 'reserved']);
    }

    public static function isVisible(Partner $partner, Vehicle $vehicle): bool
    {
        return ! $vehicle->trashed() && self::visible($partner)->whereKey($vehicle->id)->exists();
    }

    /**
     * The same rules as visible(), in PHP, for one vehicle in memory: used to
     * judge a change before and after it happened (webhooks). Must agree with
     * the SQL version (tests/PartnerApi/Unit/PartnerScopeTest).
     */
    public static function matches(Partner $partner, Vehicle $vehicle): bool
    {
        $own = Supplier::ownStockId();
        if ($vehicle->getAttribute('deleted_at') !== null
            || ! ($vehicle->supplier_id === null || ($own !== null && (int) $vehicle->supplier_id === (int) $own))) {
            return false;
        }

        $status = $vehicle->status;
        $soldAt = $vehicle->getAttribute('sold_at');
        $visible = in_array($status, ['published', 'reserved'], true)
            || ($status === 'sold' && $soldAt !== null && \Illuminate\Support\Carbon::parse($soldAt)->gte(now()->subDays(self::SOLD_VISIBLE_DAYS)));
        if (! $visible) {
            return false;
        }

        $scope = $partner->stock_scope ?? [];
        $year = $vehicle->year_first_reg ?? $vehicle->manufacture_year;
        $price = (float) $vehicle->price_fob_discount > 0 ? (float) $vehicle->price_fob_discount : (float) $vehicle->price_fob;

        return (empty($scope['makes']) || in_array((int) $vehicle->make_id, array_map('intval', $scope['makes']), true))
            && (empty($scope['body_types']) || in_array((int) $vehicle->body_type_id, array_map('intval', $scope['body_types']), true))
            && (empty($scope['year_min']) || ($year !== null && (int) $year >= (int) $scope['year_min']))
            && (empty($scope['year_max']) || ($year !== null && (int) $year <= (int) $scope['year_max']))
            && ((empty($scope['price_min']) && empty($scope['price_max'])) || (
                ! $vehicle->price_on_request
                && (empty($scope['price_min']) || $price >= (float) $scope['price_min'])
                && (empty($scope['price_max']) || $price <= (float) $scope['price_max'])
            ));
    }

    /** Partner-facing status names. */
    public static function status(Vehicle $vehicle): string
    {
        return match ($vehicle->status) {
            'published' => 'available',
            'reserved' => 'reserved',
            'sold' => 'sold',
            default => 'removed',
        };
    }

    /** Count of vehicles a scope setting would show, for the admin preview. */
    public static function previewCount(Partner $partner): int
    {
        return self::listable($partner)->count();
    }
}
