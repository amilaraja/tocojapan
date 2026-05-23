<?php

namespace App\Http\Controllers;

use App\Cms\PageTemplateRegistry;
use App\Http\Requests\VehicleListRequest;
use App\Models\BodyType;
use App\Models\Country;
use App\Models\Make;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Contracts\View\View;

class VehicleController extends Controller
{
    public function home(): View
    {
        $hotDeals = Vehicle::query()
            ->published()
            ->featured()
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByDesc('published_at')
            ->limit(12)
            ->get();

        $latest = Vehicle::query()
            ->published()
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByDesc('published_at')
            ->limit(16)
            ->get();

        $makesWithCounts = Make::where('is_active', true)
            ->with('media')
            ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get();

        $bodyTypesWithCounts = BodyType::where('is_active', true)
            ->with('media')
            ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('published_count')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $testimonials = Testimonial::query()
            ->featured()
            ->with('media')
            ->orderByDesc('created_at')
            ->orderBy('sort_order')
            ->limit(10)
            ->get();

        // Resolve the editable Home page content from the CMS, if present.
        // The HomeTemplate's render() pulls the page record and merges
        // hardcoded defaults — see app/Cms/Templates/HomeTemplate.php.
        $page = Page::where('slug', 'home')->first();
        $template = PageTemplateRegistry::resolve('home');

        $shared = [
            'hotDeals' => $hotDeals,
            'latest' => $latest,
            // Back-compat: existing partials still reference $featured.
            'featured' => $latest,
            'makesWithCounts' => $makesWithCounts,
            'bodyTypesWithCounts' => $bodyTypesWithCounts,
            'allMakes' => Make::where('is_active', true)
                ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'name']),
            'allBodyTypes' => BodyType::where('is_active', true)
                ->with('media')
                ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
                ->orderBy('name')->get(),
            'totalPublished' => Vehicle::query()->published()->count(),
            'testimonials' => $testimonials,
        ];

        if ($page && $page->isPublished() && $template) {
            return $template::render($page)->with($shared);
        }

        // Fallback: render with empty $content so the Blade defaults kick in.
        return view('home', array_merge(['content' => []], $shared));
    }

    /**
     * Returns a rendered horizontal-scroll strip of vehicle cards for the
     * client-side "Recently viewed" block. Accepts ?slugs=foo,bar,baz and
     * preserves that order. Cards capped at 8.
     */
    public function recentlyViewed(\Illuminate\Http\Request $request): View|\Illuminate\Http\Response
    {
        $raw = (string) $request->query('slugs', '');
        $slugs = array_values(array_filter(array_slice(array_map('trim', explode(',', $raw)), 0, 8)));

        if (empty($slugs)) {
            return response('', 204);
        }

        $vehicles = Vehicle::query()
            ->published()
            ->whereIn('slug', $slugs)
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->get()
            ->sortBy(fn ($v) => array_search($v->slug, $slugs, true))
            ->values();

        if ($vehicles->isEmpty()) {
            return response('', 204);
        }

        return view('partials.home-recently-viewed-cards', ['vehicles' => $vehicles]);
    }

    public function index(VehicleListRequest $request): View
    {
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'latest';
        $perPage = (int) ($filters['per_page'] ?? 20);

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
            'makes' => Make::where('is_active', true)
                ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'name']),
            'bodyTypes' => BodyType::where('is_active', true)
                ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
                ->orderBy('name')->get(['id', 'slug', 'name']),
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
            ->with([
                'ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'importRegulations' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'importRegulations.ports',
            ])
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
