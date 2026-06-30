<?php

namespace App\Http\Controllers\Api;

use App\Domains\Opening\Services\OpeningBalanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningBalance\ListOpeningBalanceRequest;
use App\Http\Requests\OpeningBalance\StoreOpeningBalanceRequest;
use App\Http\Resources\OpeningBalanceBatchResource;
use App\Models\OpeningBalanceBatch;
use App\Services\IdempotencyService;
use App\Services\OpeningBalanceQueryService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class OpeningBalanceController extends Controller
{
    public function __construct(
        private readonly OpeningBalanceService $service,
        private readonly OpeningBalanceQueryService $query,
        private readonly IdempotencyService $idempotency,
    ) {}

    #[OA\Get(
        path: '/opening-balances',
        summary: 'Daftar batch saldo awal',
        tags: ['OpeningBalance'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListOpeningBalanceRequest $request): JsonResponse
    {
        $this->authorize('viewAny', OpeningBalanceBatch::class);

        return $this->collection(OpeningBalanceBatchResource::collection($this->query->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/opening-balances',
        summary: 'Posting batch saldo awal ke ledger',
        tags: ['OpeningBalance'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreOpeningBalanceRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $batch = $this->service->create($request->validated(), $request->user());

            return $this->created(new OpeningBalanceBatchResource($this->service->findForShow($batch)));
        });
    }

    #[OA\Get(
        path: '/opening-balances/{openingBalanceBatch}',
        summary: 'Detail batch saldo awal',
        tags: ['OpeningBalance'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(OpeningBalanceBatch $openingBalanceBatch): JsonResponse
    {
        $this->authorize('view', $openingBalanceBatch);

        return $this->resource(new OpeningBalanceBatchResource($this->service->findForShow($openingBalanceBatch)));
    }
}
