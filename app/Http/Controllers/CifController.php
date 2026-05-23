<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;

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
}
