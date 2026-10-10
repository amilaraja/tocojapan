<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Models\Vehicle;
use App\Modules\PartnerApi\Domain\Stock\PartnerPricing;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Http\Resources\PartnerVehicleResource;
use App\Modules\PartnerApi\Models\PartnerAccessToken;
use App\Modules\PartnerApi\Support\ETag;
use App\Modules\PartnerApi\Support\PartnerApiException;
use App\Modules\PartnerApi\Support\StrictInput;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /vehicles and GET /vehicles/{stock_no} (FR-STK-01/02, FR-SYNC-01).
 */
class VehicleController
{
    public const MAX_LIMIT = 100;

    public const SYNC_WINDOW_DAYS = 30;

    private const FILTERS = ['make', 'model', 'body_type', 'year_min', 'year_max', 'price_min', 'price_max', 'status'];

    private const RELATIONS = ['make', 'vehicleModel', 'bodyType', 'media'];

    public function index(Request $request): Response
    {
        StrictInput::only($request, [...self::FILTERS, 'limit', 'cursor', 'updated_since']);

        $data = Validator::make($request->query(), [
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'cursor' => ['nullable', 'string', 'max:500'],
            'updated_since' => ['nullable', 'date'],
            'make' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'body_type' => ['nullable', 'string', 'max:80'],
            'year_min' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'year_max' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:available,reserved'],
        ])->validate();

        $token = $this->token($request);
        $partner = $token->client->partner;
        $limit = (int) ($data['limit'] ?? 50);

        $cursor = null;
        if (! empty($data['cursor'])) {
            $cursor = Cursor::fromEncoded($data['cursor']);
            if ($cursor === null) {
                throw new PartnerApiException(400, 'invalid_cursor', 'The cursor is not valid.', 'Use next_cursor exactly as returned, or start again without it.');
            }
        }

        $since = null;
        if (! empty($data['updated_since'])) {
            $since = CarbonImmutable::parse($data['updated_since'])->utc();
            if ($since->lt(now()->subDays(self::SYNC_WINDOW_DAYS))) {
                throw new PartnerApiException(400, 'sync_window_exceeded', 'updated_since is too far back.',
                    'Removed vehicles are reported for '.self::SYNC_WINDOW_DAYS.' days. Do a full sync instead.');
            }
        }

        $query = $since
            ? $this->changesSince($partner, $since)
            : $this->filtered(PartnerScope::listable($partner), $data, $partner)->orderBy('vehicles.id');

        $page = $query->with(self::RELATIONS)->cursorPaginate($limit, ['vehicles.*'], 'cursor', $cursor);

        $visibleIds = $since
            ? $this->filtered(PartnerScope::visible($partner), $data, $partner)
                ->whereIn('vehicles.id', $page->getCollection()->pluck('id'))->pluck('vehicles.id')->flip()
            : null;

        $items = $page->getCollection()->map(fn (Vehicle $vehicle) => $visibleIds === null || isset($visibleIds[$vehicle->id])
            ? PartnerVehicleResource::make($vehicle, $token->client)
            : PartnerVehicleResource::tombstone($vehicle));

        return ETag::respond($request, [
            'data' => $items->values()->all(),
            'meta' => [
                'limit' => $limit,
                'next_cursor' => $page->nextCursor()?->encode(),
                'scope_version' => $partner->scope_version,
                'sync' => $since ? 'changes' : 'full',
            ],
        ]);
    }

    public function show(Request $request, string $stockNo): Response
    {
        StrictInput::only($request, []);
        $token = $this->token($request);

        // Out of scope and unknown look the same: 404, never 403 (FR-STK-02).
        $vehicle = PartnerScope::visible($token->client->partner)
            ->where('vehicles.stock_no', $stockNo)->with(self::RELATIONS)->first();

        if (! $vehicle) {
            throw new PartnerApiException(404, 'not_found', 'Not found.');
        }

        return ETag::respond($request, ['data' => PartnerVehicleResource::make($vehicle, $token->client)]);
    }

    /**
     * Delta sync: every own-stock vehicle that was ever published and changed
     * since the given time, trashed ones included, oldest change first. Not
     * narrowed by the partner's scope, so a car that moved out of scope still
     * reaches the partner, as a tombstone (stock number only).
     *
     * @return Builder<Vehicle>
     */
    private function changesSince($partner, CarbonImmutable $since): Builder
    {
        return Vehicle::withTrashed()
            ->whereIn('vehicles.id', PartnerScope::ownStock()->withTrashed()->whereNotNull('vehicles.published_at')->select('vehicles.id'))
            ->where('vehicles.updated_at', '>', $since)
            ->orderBy('vehicles.updated_at')->orderBy('vehicles.id');
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    private function filtered(Builder $query, array $data, $partner): Builder
    {
        if (! empty($data['make'])) {
            $query->whereHas('make', fn ($query) => $query->where('slug', $data['make']));
        }
        if (! empty($data['model'])) {
            $query->whereHas('vehicleModel', fn ($query) => $query->where('slug', $data['model']));
        }
        if (! empty($data['body_type'])) {
            $query->whereHas('bodyType', fn ($query) => $query->where('slug', $data['body_type']));
        }
        if (! empty($data['year_min'])) {
            $query->whereRaw(PartnerScope::YEAR_SQL.' >= ?', [(int) $data['year_min']]);
        }
        if (! empty($data['year_max'])) {
            $query->whereRaw(PartnerScope::YEAR_SQL.' <= ?', [(int) $data['year_max']]);
        }
        if (isset($data['price_min']) || isset($data['price_max'])) {
            if ($partner->price_mode === PartnerPricing::ON_REQUEST) {
                throw new PartnerApiException(400, 'price_filter_unavailable', 'Price filters are not available for your account.',
                    'Your prices are on request.');
            }
            $query->where('vehicles.price_on_request', false);
            if (isset($data['price_min'])) {
                $query->whereRaw(PartnerScope::PRICE_SQL.' >= ?', [PartnerPricing::toBase((float) $data['price_min'], $partner)]);
            }
            if (isset($data['price_max'])) {
                $query->whereRaw(PartnerScope::PRICE_SQL.' <= ?', [PartnerPricing::toBase((float) $data['price_max'], $partner)]);
            }
        }
        if (! empty($data['status'])) {
            $query->where('vehicles.status', $data['status'] === 'available' ? 'published' : 'reserved');
        }

        return $query;
    }

    private function token(Request $request): PartnerAccessToken
    {
        return $request->attributes->get(AuthenticatePartner::TOKEN);
    }
}
