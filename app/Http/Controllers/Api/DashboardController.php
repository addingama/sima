<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    #[OA\Get(
        path: '/dashboard',
        summary: 'Ringkasan dashboard',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(): JsonResponse
    {
        return $this->ok($this->service->summary());
    }
}
