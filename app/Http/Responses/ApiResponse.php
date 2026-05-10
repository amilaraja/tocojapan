<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function ok(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => (object) $meta,
            'errors' => null,
        ], $status);
    }

    public static function created(mixed $data): JsonResponse
    {
        return self::ok($data, [], 201);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => (object) [],
            'errors' => [
                'message' => $message,
                'fields' => (object) $errors,
            ],
        ], $status);
    }
}
