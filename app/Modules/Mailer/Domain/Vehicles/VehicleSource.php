<?php

namespace App\Modules\Mailer\Domain\Vehicles;

use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only access to tocojapan.com vehicles (TOC-VEH-001). Never calls
 * save/update/delete on Vehicle; enforced by VehicleSourceTest.
 *
 * Availability (TOC-VEH-003): only status "published" is selectable. The
 * site has no "reserved" status yet, so $includeReserved is accepted for
 * Mailer Admins but currently changes nothing. Sold, draft and deleted
 * vehicles never appear in search.
 */
class VehicleSource
{
    public const PER_PAGE = 20; // TOC-VEH-004

    /** Relations every DTO needs, eager loaded to avoid N+1. */
    protected const WITH = ['make', 'vehicleModel', 'bodyType', 'media'];

    /**
     * Filters (TOC-VEH-004): q (stock ref or keyword), make (slug),
     * body_type (slug), price_from, price_to, badge (hot_deal | new).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, VehicleDTO>
     */
    public function search(array $filters = [], int $page = 1, bool $includeReserved = false): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));

        $query = Vehicle::query()
            ->with(self::WITH)
            ->where('status', 'published')
            // Reuse the storefront filter scope (keyword, make, body type,
            // effective-price range, featured) instead of duplicating it.
            ->filter([
                'q' => $q,
                'make' => $filters['make'] ?? null,
                'body_type' => $filters['body_type'] ?? null,
                'price_from' => $filters['price_from'] ?? null,
                'price_to' => $filters['price_to'] ?? null,
                'featured' => ($filters['badge'] ?? null) === VehicleDTO::BADGE_HOT_DEAL,
            ])
            ->when(($filters['badge'] ?? null) === VehicleDTO::BADGE_NEW,
                fn (Builder $b) => $b->whereIn('id', Vehicle::latestArrivalIds()));

        // An exact stock ref match always comes first (E02056 → that vehicle).
        if ($q !== '') {
            $query->orderByRaw('CASE WHEN stock_no = ? OR ref_no = ? THEN 0 ELSE 1 END', [$q, $q]);
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page))
            ->through(fn (Vehicle $v) => $this->toDto($v));
    }

    /** Any vehicle by id, whatever its status (for re-checks); null when gone for good. */
    public function find(int $id): ?VehicleDTO
    {
        $vehicle = Vehicle::withTrashed()->with(self::WITH)->find($id);

        return $vehicle ? $this->toDto($vehicle) : null;
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, VehicleDTO> keyed by vehicle id; missing ids are absent
     */
    public function findMany(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Vehicle::withTrashed()
            ->with(self::WITH)
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Vehicle $v) => [$v->id => $this->toDto($v)]);
    }

    public function toDto(Vehicle $v): VehicleDTO
    {
        $photo = $v->getFirstMedia('photos');

        return new VehicleDTO(
            id: $v->id,
            stockRef: $v->stock_no ?: ($v->ref_no ?: null),
            title: (string) $v->title,
            make: $v->make?->name,
            model: $v->vehicleModel?->name,
            bodyType: $v->bodyType?->name,
            modelYear: $v->manufacture_year,
            registrationYear: $v->year_first_reg,
            mileageKm: $v->mileage_km,
            transmission: $v->transmission ? ucfirst(strtolower((string) $v->transmission)) : null,
            priceFob: $v->effectivePriceFob(),
            previousPrice: $v->isDiscounted() ? (float) $v->price_fob : null,
            priceOnRequest: (bool) $v->price_on_request,
            badge: $this->badgeFor($v),
            status: $this->statusFor($v),
            photoUrl: $photo?->getUrl(),
            photoPath: $photo?->getPath(),
            url: route('vehicles.show', $v->slug),
        );
    }

    /** Same precedence as the site card: Hot Deal over New. */
    protected function badgeFor(Vehicle $v): ?string
    {
        if ($v->status !== 'published') {
            return null;
        }
        if ($v->is_featured) {
            return VehicleDTO::BADGE_HOT_DEAL;
        }

        return $v->isNewArrival() ? VehicleDTO::BADGE_NEW : null;
    }

    protected function statusFor(Vehicle $v): string
    {
        return match (true) {
            $v->trashed() => VehicleDTO::STATUS_UNAVAILABLE,
            $v->status === 'sold' => VehicleDTO::STATUS_SOLD,
            $v->status === 'published' => VehicleDTO::STATUS_AVAILABLE,
            default => VehicleDTO::STATUS_UNAVAILABLE,
        };
    }
}
