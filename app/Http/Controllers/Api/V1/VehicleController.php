<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleListRequest;
use App\Http\Resources\VehicleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    public function index(VehicleListRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'latest';
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = Vehicle::query()
            ->published()
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->filter($filters);

        if ($sort === 'price_asc') {
            $query->orderByRaw('COALESCE(price_fob_discount, price_fob) asc');
        } elseif ($sort === 'price_desc') {
            $query->orderByRaw('COALESCE(price_fob_discount, price_fob) desc');
        } else {
            $query->orderBy(...self::sortColumns($sort));
        }

        $page = $query->paginate($perPage)->withQueryString();

        return ApiResponse::ok(
            VehicleResource::collection($page)->resolve(),
            [
                'pagination' => [
                    'total' => $page->total(),
                    'per_page' => $page->perPage(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                ],
                'filters' => $filters,
            ]
        );
    }

    public function show(string $slug): JsonResponse
    {
        $vehicle = Vehicle::query()
            ->published()
            ->where('slug', $slug)
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->firstOrFail();

        return ApiResponse::ok((new VehicleResource($vehicle))->resolve(request()));
    }

    /**
     * Live count for the homepage / listing search form — returns just the
     * matching vehicle count for the supplied filter combination so the UI
     * can show "42 vehicles match" without paging the whole list.
     */
    public function count(VehicleListRequest $request): JsonResponse
    {
        $count = Vehicle::query()
            ->published()
            ->filter($request->validated())
            ->count();

        return ApiResponse::ok(['count' => $count]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function sortColumns(string $sort): array
    {
        return match ($sort) {
            'year_asc' => ['year_first_reg', 'asc'],
            'year_desc' => ['year_first_reg', 'desc'],
            default => ['published_at', 'desc'],
        };
    }
}
