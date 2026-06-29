<?php

namespace App\Http\Controllers\Api;

use App\Domains\Audit\Services\AuditQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ListAuditRequest;
use App\Http\Resources\AuditResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OwenIt\Auditing\Models\Audit;

/** Audit Trail (read-only). */
class AuditController extends Controller
{
    public function __construct(private readonly AuditQueryService $service) {}

    #[OA\Get(
        path: '/audits',
        summary: 'Daftar audit trail',
        tags: ['Audit'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListAuditRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Audit::class);

        return $this->collection(AuditResource::collection($this->service->paginate($request->listQuery(25))));
    }

    #[OA\Get(
        path: '/audits/{audit}',
        summary: 'Detail audit trail',
        tags: ['Audit'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(Audit $audit): JsonResponse
    {
        $this->authorize('view', $audit);

        return $this->resource(new AuditResource($this->service->find($audit->id)));
    }
}
