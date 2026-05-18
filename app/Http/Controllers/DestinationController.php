<?php

namespace App\Http\Controllers;

use App\Models\Port;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DestinationController extends Controller
{
    public function set(Request $request): Response|RedirectResponse
    {
        $portId = (int) $request->input('port_id');
        $ajax = $request->ajax() || $request->expectsJson();

        if ($portId <= 0) {
            $forget = cookie()->forget('toco_port');

            return $ajax
                ? response()->noContent()->withCookie($forget)
                : back()->withCookie($forget);
        }

        $port = Port::query()->where('id', $portId)->where('is_active', true)->first();
        if (! $port) {
            return $ajax ? response()->noContent() : back();
        }

        $cookie = cookie('toco_port', (string) $port->id, 60 * 24 * 365);

        return $ajax
            ? response()->noContent()->withCookie($cookie)
            : back()->withCookie($cookie);
    }

    public function clear(): RedirectResponse
    {
        return back()->withCookie(cookie()->forget('toco_port'));
    }
}
