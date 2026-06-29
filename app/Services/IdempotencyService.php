<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan & mengembalikan respons untuk Idempotency-Key yang sama.
 * Dipakai pada POST finansial agar retry jaringan tidak membuat duplikat.
 */
class IdempotencyService
{
    private const TTL_HOURS = 24;

    public function resolve(Request $request, callable $handler): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $handler();
        }

        if (strlen($key) > 128) {
            return response()->json(['message' => 'Idempotency-Key terlalu panjang (maks 128 karakter).'], 422);
        }

        /** @var User $user */
        $user = $request->user();
        $route = $request->route()?->getName() ?? $request->path();

        $existing = DB::table('idempotency_keys')
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->where('route', $route)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            return response()->json(
                json_decode($existing->response_body, true),
                (int) $existing->response_status
            );
        }

        /** @var JsonResponse $response */
        $response = $handler();

        DB::table('idempotency_keys')->insert([
            'user_id' => $user->id,
            'key' => $key,
            'route' => $route,
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
            'expires_at' => now()->addHours(self::TTL_HOURS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $response;
    }
}
