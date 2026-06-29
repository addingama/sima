<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Health check untuk load balancer / monitoring (tanpa auth). */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'fail';
        }

        try {
            Cache::store(config('cache.default'))->put('sima_health_probe', 1, 10);
            $checks['cache'] = 'ok';
        } catch (\Throwable) {
            $checks['cache'] = 'fail';
        }

        $healthy = ! in_array('fail', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => config('app.name'),
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
