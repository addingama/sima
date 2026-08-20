<?php

namespace App\Http\Controllers\Api;

use App\Domains\Transfer\Services\TransferService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\ListAccountTransferRequest;
use App\Http\Requests\Transfer\PostAccountTransferRequest;
use App\Http\Requests\Transfer\ReverseAccountTransferRequest;
use App\Http\Requests\Transfer\StoreAccountTransferRequest;
use App\Http\Resources\AccountTransferResource;
use App\Models\AccountTransfer;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AccountTransferController extends Controller
{
    public function __construct(
        private readonly TransferService $service,
        private readonly IdempotencyService $idempotency,
    ) {}

    #[OA\Get(
        path: '/account-transfers',
        summary: 'Daftar transfer antar rekening',
        tags: ['AccountTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListAccountTransferRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AccountTransfer::class);

        return $this->collection(AccountTransferResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/account-transfers',
        summary: 'Buat transfer antar rekening (draft)',
        tags: ['AccountTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreAccountTransferRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $transfer = $this->service->create($request->validated(), $request->user());

            return $this->created(new AccountTransferResource($this->service->findForShow($transfer)));
        });
    }

    #[OA\Get(
        path: '/account-transfers/{accountTransfer}',
        summary: 'Detail transfer antar rekening',
        tags: ['AccountTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(AccountTransfer $accountTransfer): JsonResponse
    {
        $this->authorize('view', $accountTransfer);

        return $this->resource(new AccountTransferResource($this->service->findForShow($accountTransfer)));
    }

    #[OA\Post(
        path: '/account-transfers/{accountTransfer}/post',
        summary: 'Post transfer ke ledger',
        tags: ['AccountTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function post(PostAccountTransferRequest $request, AccountTransfer $accountTransfer): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $accountTransfer): JsonResponse {
            return $this->resource(new AccountTransferResource(
                $this->service->findForShow($this->service->post($accountTransfer, $request->user()))
            ));
        });
    }

    #[OA\Post(
        path: '/account-transfers/{accountTransfer}/reverse',
        summary: 'Batalkan transfer (reversal)',
        tags: ['AccountTransfer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reverse(ReverseAccountTransferRequest $request, AccountTransfer $accountTransfer): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $accountTransfer): JsonResponse {
            return $this->resource(new AccountTransferResource(
                $this->service->findForShow(
                    $this->service->reverse($accountTransfer, $request->user(), $request->validated('reason'))
                )
            ));
        });
    }
}
