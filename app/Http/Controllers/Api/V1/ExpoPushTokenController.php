<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ExpoPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpoPushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'in:ios,android,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        /** @var ExpoPushToken $record */
        $record = $request->user()->expoPushTokens()->updateOrCreate(
            ['token' => $data['token']],
            [
                'platform' => $data['platform'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return ApiResponse::ok([
            'id' => $record->id,
            'token' => $record->token,
            'last_seen_at' => $record->last_seen_at,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $request->user()->expoPushTokens()->where('token', $data['token'])->delete();

        return ApiResponse::ok(['message' => 'Token removed.']);
    }
}
