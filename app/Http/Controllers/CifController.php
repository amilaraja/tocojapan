<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Vehicle;
use App\Support\CifDestinations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class CifController extends Controller
{
    public function index(): View
    {
        return view('cif.index', [
            'countries' => Country::query()
                ->where('is_active', true)
                ->with(['ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
            'vehicles' => Vehicle::query()
                ->published()
                ->whereNotNull('m3')
                ->where('price_on_request', false)
                ->orderByDesc('published_at')
                ->limit(50)
                ->get(['id', 'slug', 'title', 'price_fob', 'price_fob_discount', 'currency', 'm3', 'ref_no']),
        ]);
    }

    /**
     * Countries/ports/import rules for the listing quick-view CIF calculator,
     * fetched once on first use (too heavy to inline in every listing page).
     */
    public function destinations(): JsonResponse
    {
        return response()
            ->json(CifDestinations::payload())
            ->header('Cache-Control', 'public, max-age=600');
    }
}
