<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/** Health check untuk load balancer / monitoring (tanpa auth). */
class HealthController extends Controller
{
    #[OA\Get(
        path: '/health',
        summary: 'Health check',
        tags: ['Health'],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
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

        return ApiResponse::success([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => config('app.name'),
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], null, null, $healthy ? 200 : 503);
    }
}
