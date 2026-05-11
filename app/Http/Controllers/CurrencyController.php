<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class CurrencyController extends Controller
{
    public function set(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $code), 0, 3));
        $valid = Currency::query()->where('code', $code)->where('is_active', true)->exists();
        if (! $valid) {
            return back();
        }

        if ($user = $request->user()) {
            $user->preferred_currency = $code;
            $user->saveQuietly();
        }

        return back()->withCookie(cookie('toco_currency', $code, 60 * 24 * 365));
    }
}
