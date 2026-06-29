<?php

namespace App\Http\Controllers\Api;

use App\Domains\Expense\Services\BankFeeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankFee\ListBankFeeRequest;
use App\Http\Requests\BankFee\PostBankFeeRequest;
use App\Http\Requests\BankFee\ReverseBankFeeRequest;
use App\Http\Requests\BankFee\StoreBankFeeRequest;
use App\Http\Resources\BankFeeResource;
use App\Models\BankFee;
use App\Services\BankFeeQueryService;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BankFeeController extends Controller
{
    public function __construct(
        private readonly BankFeeService $service,
        private readonly BankFeeQueryService $query,
        private readonly IdempotencyService $idempotency,
    ) {}

    #[OA\Get(
        path: '/bank-fees',
        summary: 'Daftar biaya bank',
        tags: ['BankFee'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListBankFeeRequest $request): JsonResponse
    {
        $this->authorize('viewAny', BankFee::class);

        return $this->collection(BankFeeResource::collection($this->query->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/bank-fees',
        summary: 'Buat biaya bank',
        tags: ['BankFee'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreBankFeeRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $fee = $this->service->create($request->validated(), $request->user());

            return $this->created(new BankFeeResource($this->service->findForShow($fee)));
        });
    }

    #[OA\Get(
        path: '/bank-fees/{bankFee}',
        summary: 'Detail biaya bank',
        tags: ['BankFee'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(BankFee $bankFee): JsonResponse
    {
        $this->authorize('view', $bankFee);

        return $this->resource(new BankFeeResource($this->service->findForShow($bankFee)));
    }

    #[OA\Post(
        path: '/bank-fees/{bankFee}/post',
        summary: 'Post biaya bank ke ledger',
        tags: ['BankFee'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function post(PostBankFeeRequest $request, BankFee $bankFee): JsonResponse
    {
        $this->authorize('post', $bankFee);

        return $this->idempotency->resolve($request, function () use ($request, $bankFee): JsonResponse {
            return $this->resource(new BankFeeResource($this->service->post($bankFee, $request->user())));
        });
    }

    #[OA\Post(
        path: '/bank-fees/{bankFee}/reverse',
        summary: 'Batalkan biaya bank (reversal)',
        tags: ['BankFee'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reverse(ReverseBankFeeRequest $request, BankFee $bankFee): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $bankFee): JsonResponse {
            return $this->resource(new BankFeeResource(
                $this->service->reverse($bankFee, $request->user(), $request->validated('reason'))
            ));
        });
    }
}
