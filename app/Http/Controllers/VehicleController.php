<?php

namespace App\Http\Controllers;

use App\Cms\PageTemplateRegistry;
use App\Http\Requests\VehicleListRequest;
use App\Models\BodyType;
use App\Models\Country;
use App\Models\Make;
use App\Models\Page;
use App\Models\Supplier;
use App\Models\Testimonial;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

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
            ->visibleIn('show_on_homepage')
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByDesc('published_at')
            ->limit(16)
            ->get();

        // Dealer Stock carousel: partner/supplier vehicles only, priced and with photos.
        $dealerStock = Vehicle::query()
            ->published()
            ->partnerStock()
            ->where('price_on_request', false)
            ->whereNotNull('external_photos')
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $makesWithCounts = Vehicle::withPublishedCounts(Make::where('is_active', true)
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get(), 'make_id');

        $bodyTypesWithCounts = Vehicle::withPublishedCounts(BodyType::where('is_active', true)
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get(), 'body_type_id');

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
            'dealerStock' => $dealerStock,
            'dealerStockSupplier' => 'partners',
            'latest' => $latest,
            // Back-compat: existing partials still reference $featured.
            'featured' => $latest,
            'makesWithCounts' => $makesWithCounts,
            'bodyTypesWithCounts' => $bodyTypesWithCounts,
            'allMakes' => Vehicle::withPublishedCounts(Make::where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'name']), 'make_id'),
            'allBodyTypes' => Vehicle::withPublishedCounts(BodyType::where('is_active', true)
                ->with('media')
                ->orderBy('name')->get(), 'body_type_id'),
            // Cheap, serialization-safe cache: a single integer. The published
            // COUNT(*) scans the vehicles table on every homepage hit; the
            // number barely moves, so cache it for 10 minutes. (We deliberately
            // do NOT cache the model collections — their Spatie media graph does
            // not round-trip through the database cache store reliably.)
            'totalPublished' => Cache::remember(
                'home.total_published',
                now()->addMinutes(10),
                fn () => Vehicle::query()->published()->count(),
            ),
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
    public function recentlyViewed(Request $request): View|Response
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

        $query = Vehicle::query()
            ->published()
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->filter($filters);

        // Price sorts use the effective price (discount when present);
        // other sorts use a plain column.
        if ($sort === 'price_asc') {
            $query->orderByRaw('COALESCE(price_fob_discount, price_fob) asc');
        } elseif ($sort === 'price_desc') {
            $query->orderByRaw('COALESCE(price_fob_discount, price_fob) desc');
        } else {
            // Default "latest" keeps own stock above supplier feeds;
            // explicit sorts compare all stock evenly.
            if ($sort === 'latest') {
                $query->orderBySupplierPriority();
            }
            $query->orderBy(...self::sortColumns($sort));
        }

        $vehicles = $query->paginate($perPage)->withQueryString();

        return view('vehicles.index', [
            'vehicles' => $vehicles,
            'filters' => $filters,
            'makes' => Vehicle::withPublishedCounts(Make::where('is_active', true)
                ->with('media')
                ->orderBy('sort_order')->orderBy('name')->get(), 'make_id'),
            'bodyTypes' => Vehicle::withPublishedCounts(BodyType::where('is_active', true)
                ->with('media')
                ->orderBy('sort_order')->orderBy('name')->get(), 'body_type_id'),
            'destCountries' => Country::query()
                ->where('is_active', true)
                ->with(['ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'iso2']),
            'suppliers' => Supplier::query()->where('is_active', true)
                ->whereHas('vehicles', fn ($q) => $q->where('status', 'published'))
                ->orderBy('sort_priority')->orderBy('name')->get(['id', 'slug', 'name', 'is_own_stock']),
            'models' => isset($filters['make'])
                ? VehicleModel::whereHas('make', fn ($q) => $q->where('slug', $filters['make']))
                    ->orderBy('name')->get(['id', 'slug', 'name', 'make_id'])
                : collect(),
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $vehicle = Vehicle::query()
            ->published()
            ->where('slug', $slug)
            ->with(['make', 'vehicleModel', 'bodyType', 'media', 'supplier'])
            ->first();

        if (! $vehicle) {
            return $this->redirectGoneVehicle($slug);
        }

        $countries = Country::query()
            ->where('is_active', true)
            ->with([
                'ports' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
                'importRegulations' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'importRegulations.ports',
            ])
            ->orderBy('name')
            ->get();

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'countries' => $countries,
            'relatedVehicles' => $vehicle->relatedVehicles(8),
        ]);
    }

    /**
     * A vehicle that existed but is no longer listed (supplier delisted it,
     * sold more than 90 days ago, deleted) keeps its URL useful: 301 to the
     * listing for the same make/model instead of a 404.
     */
    private function redirectGoneVehicle(string $slug): RedirectResponse
    {
        $gone = Vehicle::withTrashed()->where('slug', $slug)->with(['make', 'vehicleModel'])->first();
        // Drafts were never public — keep them a plain 404.
        abort_if(! $gone || ($gone->status === 'draft' && ! $gone->trashed()), 404);

        return redirect()->route('vehicles.index', array_filter([
            'make' => $gone->make?->slug,
            'vehicle_model' => $gone->vehicleModel?->slug,
        ]), 301);
    }

    /**
     * Old WordPress "/one-price" stock page → the OnePrice-filtered listing
     * (plain listing while no OnePrice stock is live).
     */
    public function legacyOnePriceIndex(): RedirectResponse
    {
        $live = Vehicle::query()->published()
            ->whereHas('supplier', fn ($q) => $q->where('slug', 'oneprice'))
            ->exists();

        return redirect()->route('vehicles.index', $live ? ['supplier' => 'oneprice'] : [], 301);
    }

    /**
     * Old WordPress OnePrice plugin URLs: /vehicle/{OnePrice id}.
     */
    public function legacyOnePrice(string $id): RedirectResponse
    {
        $slug = Vehicle::withTrashed()
            ->whereHas('supplier', fn ($q) => $q->where('slug', 'oneprice'))
            ->where('supplier_ref', $id)
            ->value('slug');

        return $slug
            ? redirect()->route('vehicles.show', $slug, 301)
            : redirect()->route('vehicles.index', ['supplier' => 'oneprice'], 301);
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
