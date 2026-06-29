<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan & mengembalikan respons untuk Idempotency-Key yang sama.
 * Claim key dalam transaksi DB untuk mencegah duplikat pada request konkuren.
 */
class IdempotencyService
{
    private const TTL_HOURS = 24;

    private const STATUS_PROCESSING = 'processing';

    private const STATUS_COMPLETED = 'completed';

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

        return DB::transaction(function () use ($user, $key, $route, $handler): JsonResponse {
            $existing = DB::table('idempotency_keys')
                ->where('user_id', $user->id)
                ->where('key', $key)
                ->where('route', $route)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->expires_at > now()) {
                return $this->responseFromRow($existing);
            }

            if ($existing !== null) {
                DB::table('idempotency_keys')->where('id', $existing->id)->delete();
            }

            $claimId = $this->claimKey($user->id, $key, $route);

            if ($claimId === null) {
                $row = DB::table('idempotency_keys')
                    ->where('user_id', $user->id)
                    ->where('key', $key)
                    ->where('route', $route)
                    ->first();

                return $row !== null
                    ? $this->responseFromRow($row)
                    : response()->json(['message' => 'Permintaan sedang diproses.'], 409);
            }

            /** @var JsonResponse $response */
            $response = $handler();

            DB::table('idempotency_keys')->where('id', $claimId)->update([
                'status' => self::STATUS_COMPLETED,
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'updated_at' => now(),
            ]);

            return $response;
        });
    }

    private function claimKey(int $userId, string $key, string $route): ?int
    {
        try {
            return DB::table('idempotency_keys')->insertGetId([
                'user_id' => $userId,
                'key' => $key,
                'route' => $route,
                'status' => self::STATUS_PROCESSING,
                'response_status' => 0,
                'response_body' => '{}',
                'expires_at' => now()->addHours(self::TTL_HOURS),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    private function responseFromRow(object $row): JsonResponse
    {
        if ($row->status === self::STATUS_PROCESSING) {
            return response()->json(['message' => 'Permintaan sedang diproses.'], 409);
        }

        return response()->json(
            json_decode($row->response_body, true),
            (int) $row->response_status
        );
    }
}
