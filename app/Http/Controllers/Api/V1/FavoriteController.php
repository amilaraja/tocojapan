<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicles = Vehicle::query()
            ->whereIn('id', $request->user()->favorites()->pluck('vehicle_id'))
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByDesc('published_at')
            ->limit(60)
            ->get();

        return ApiResponse::ok(VehicleResource::collection($vehicles)->resolve());
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $vehicle = Vehicle::where('slug', $slug)->firstOrFail();

        $request->user()->favorites()->firstOrCreate(['vehicle_id' => $vehicle->id]);

        return ApiResponse::ok(['favorited' => true, 'vehicle_slug' => $vehicle->slug]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $vehicle = Vehicle::where('slug', $slug)->firstOrFail();

        $request->user()->favorites()->where('vehicle_id', $vehicle->id)->delete();

        return ApiResponse::ok(['favorited' => false, 'vehicle_slug' => $vehicle->slug]);
    }
}
