<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:120'],
            'source' => ['nullable', 'string', 'max:60'],
        ]);

        $subscriber = Subscriber::updateOrCreate(
            ['email' => mb_strtolower($data['email'])],
            [
                'source' => $data['source'] ?? 'homepage_cta',
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'unsubscribed_at' => null,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => $subscriber->wasRecentlyCreated
                ? "You're on the list — we'll email you when fresh stock lands."
                : "You're already subscribed — thanks!",
        ]);
    }
}
