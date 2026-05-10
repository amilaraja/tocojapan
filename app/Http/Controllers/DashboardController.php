<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'favoritesCount' => $user->favorites()->count(),
            'openQuotesCount' => $user->quotes()->whereNotIn('status', ['archived', 'declined'])->count(),
            'recentQuotes' => $user->quotes()->with('vehicle')->latest()->limit(5)->get(),
        ]);
    }
}
