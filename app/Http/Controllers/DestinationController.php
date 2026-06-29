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
        $target = $this->safeRedirect($request->input('redirect_to'));
        $redirect = fn () => $target ? redirect($target) : back();

        if ($portId <= 0) {
            $forget = cookie()->forget('toco_port');

            return $ajax
                ? response()->noContent()->withCookie($forget)
                : $redirect()->withCookie($forget);
        }

        $port = Port::query()->where('id', $portId)->where('is_active', true)->first();
        if (! $port) {
            return $ajax ? response()->noContent() : $redirect();
        }

        $cookie = cookie('toco_port', (string) $port->id, 60 * 24 * 365);

        return $ajax
            ? response()->noContent()->withCookie($cookie)
            : $redirect()->withCookie($cookie);
    }

    /**
     * Restrict a caller-supplied redirect target to an internal path+query so
     * setting the destination can land on e.g. a filtered /vehicles page
     * without opening an external-redirect hole.
     */
    private function safeRedirect(?string $to): ?string
    {
        if (! is_string($to) || $to === '') {
            return null;
        }

        $path = parse_url($to, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/')) {
            return null;
        }

        $query = parse_url($to, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }

    public function clear(): RedirectResponse
    {
        return back()->withCookie(cookie()->forget('toco_port'));
    }
}
