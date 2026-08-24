<?php

namespace App\Http\Controllers\Api;

use App\Domains\Transfer\Services\FundTransferService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\ListFundTransferRequest;
use App\Http\Requests\Transfer\PostFundTransferRequest;
use App\Http\Requests\Transfer\ReverseFundTransferRequest;
use App\Http\Requests\Transfer\StoreFundTransferRequest;
use App\Http\Resources\FundTransferResource;
use App\Models\FundTransfer;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class FundTransferController extends Controller
{
    public function __construct(
        private readonly FundTransferService $service,
        private readonly IdempotencyService $idempotency,
    ) {}

    #[OA\Get(
        path: '/fund-transfers',
        summary: 'Daftar transfer antar Dana Amanah',
        tags: ['FundTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListFundTransferRequest $request): JsonResponse
    {
        $this->authorize('viewAny', FundTransfer::class);

        return $this->collection(FundTransferResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/fund-transfers',
        summary: 'Buat transfer antar Dana Amanah (draft)',
        tags: ['FundTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreFundTransferRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $transfer = $this->service->create($request->validated(), $request->user());

            return $this->created(new FundTransferResource($this->service->findForShow($transfer)));
        });
    }

    #[OA\Get(
        path: '/fund-transfers/{fundTransfer}',
        summary: 'Detail transfer antar Dana Amanah',
        tags: ['FundTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(FundTransfer $fundTransfer): JsonResponse
    {
        $this->authorize('view', $fundTransfer);

        return $this->resource(new FundTransferResource($this->service->findForShow($fundTransfer)));
    }

    #[OA\Post(
        path: '/fund-transfers/{fundTransfer}/post',
        summary: 'Post transfer Dana Amanah ke ledger',
        tags: ['FundTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function post(PostFundTransferRequest $request, FundTransfer $fundTransfer): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $fundTransfer): JsonResponse {
            return $this->resource(new FundTransferResource(
                $this->service->findForShow($this->service->post($fundTransfer, $request->user()))
            ));
        });
    }

    #[OA\Post(
        path: '/fund-transfers/{fundTransfer}/reverse',
        summary: 'Batalkan transfer Dana Amanah (reversal)',
        tags: ['FundTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reverse(ReverseFundTransferRequest $request, FundTransfer $fundTransfer): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $fundTransfer): JsonResponse {
            return $this->resource(new FundTransferResource(
                $this->service->findForShow(
                    $this->service->reverse($fundTransfer, $request->user(), $request->validated('reason'))
                )
            ));
        });
    }
}
