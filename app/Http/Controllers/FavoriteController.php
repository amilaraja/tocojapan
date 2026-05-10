<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $vehicles = Vehicle::query()
            ->whereIn('id', $request->user()->favorites()->pluck('vehicle_id'))
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('favorites.index', ['vehicles' => $vehicles]);
    }

    public function toggle(Request $request, string $slug): RedirectResponse
    {
        $vehicle = Vehicle::where('slug', $slug)->firstOrFail();
        $existing = $request->user()->favorites()->where('vehicle_id', $vehicle->id)->first();

        if ($existing) {
            $existing->delete();
            $message = 'Removed from your favorites.';
        } else {
            $request->user()->favorites()->create(['vehicle_id' => $vehicle->id]);
            $message = 'Saved to your favorites.';
        }

        return back()->with('flash', $message);
    }
}
