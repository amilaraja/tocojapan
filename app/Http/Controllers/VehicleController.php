<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleListRequest;
use App\Models\BodyType;
use App\Models\Country;
use App\Models\Make;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Contracts\View\View;

class VehicleController extends Controller
{
    public function home(): View
    {
        $featured = Vehicle::query()
            ->published()
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        $makesWithCounts = Make::where('is_active', true)
            ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('published_count')
            ->orderBy('name')
            ->limit(12)
            ->get();

        $bodyTypesWithCounts = BodyType::where('is_active', true)
            ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('published_count')
            ->orderBy('name')
            ->limit(8)
            ->get();

        return view('home', [
            'featured' => $featured,
            'makesWithCounts' => $makesWithCounts,
            'bodyTypesWithCounts' => $bodyTypesWithCounts,
            'allMakes' => Make::where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name']),
            'allBodyTypes' => BodyType::where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name']),
            'totalPublished' => Vehicle::query()->published()->count(),
        ]);
    }

    public function index(VehicleListRequest $request): View
    {
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'latest';
        $perPage = (int) ($filters['per_page'] ?? 12);

        $vehicles = Vehicle::query()
            ->published()
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->filter($filters)
            ->orderBy(...self::sortColumns($sort))
            ->paginate($perPage)
            ->withQueryString();

        return view('vehicles.index', [
            'vehicles' => $vehicles,
            'filters' => $filters,
            'makes' => Make::where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name']),
            'bodyTypes' => BodyType::where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name']),
            'models' => isset($filters['make'])
                ? VehicleModel::whereHas('make', fn ($q) => $q->where('slug', $filters['make']))
                    ->orderBy('name')->get(['id', 'slug', 'name', 'make_id'])
                : collect(),
        ]);
    }

    public function show(string $slug): View
    {
        $vehicle = Vehicle::query()
            ->published()
            ->where('slug', $slug)
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->firstOrFail();

        $countries = Country::query()
            ->where('is_active', true)
            ->with(['ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'countries' => $countries,
        ]);
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
