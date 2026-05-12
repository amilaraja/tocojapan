<?php

namespace App\Http\Controllers;

use App\Models\Port;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function set(Request $request): RedirectResponse
    {
        $portId = (int) $request->input('port_id');
        if ($portId <= 0) {
            return back()->withCookie(cookie()->forget('toco_port'));
        }
        $port = Port::query()->where('id', $portId)->where('is_active', true)->first();
        if (! $port) {
            return back();
        }

        return back()->withCookie(cookie('toco_port', (string) $port->id, 60 * 24 * 365));
    }

    public function clear(): RedirectResponse
    {
        return back()->withCookie(cookie()->forget('toco_port'));
    }
}
