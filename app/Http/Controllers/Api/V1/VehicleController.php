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

        $page = Vehicle::query()
            ->published()
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->filter($filters)
            ->orderBy(...self::sortColumns($sort))
            ->paginate($perPage)
            ->withQueryString();

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
     * @return array{0: string, 1: string}
     */
    private static function sortColumns(string $sort): array
    {
        return match ($sort) {
            'price_asc' => ['price_fob', 'asc'],
            'price_desc' => ['price_fob', 'desc'],
            'year_asc' => ['year_first_reg', 'asc'],
            'year_desc' => ['year_first_reg', 'desc'],
            default => ['published_at', 'desc'],
        };
    }
}
