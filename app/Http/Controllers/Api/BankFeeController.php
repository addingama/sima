<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankFee\ReverseBankFeeRequest;
use App\Http\Requests\BankFee\StoreBankFeeRequest;
use App\Http\Resources\BankFeeResource;
use App\Models\BankFee;
use App\Domains\Expense\Services\BankFeeService;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankFeeController extends Controller
{
    public function __construct(
        private readonly BankFeeService $service,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BankFee::class);

        $items = BankFee::query()
            ->with(['account:id,code,name', 'fund:id,code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->orderByDesc('fee_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return BankFeeResource::collection($items)->response();
    }

    public function store(StoreBankFeeRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $fee = $this->service->create($request->validated(), $request->user());

            return (new BankFeeResource($fee->load('account:id,code,name', 'fund:id,code,name')))
                ->response()
                ->setStatusCode(201);
        });
    }

    public function show(BankFee $bankFee): JsonResponse
    {
        $this->authorize('view', $bankFee);

        return (new BankFeeResource($bankFee->load([
            'account:id,code,name',
            'fund:id,code,name',
            'operationalLiability',
            'attachments',
        ])))->response();
    }

    public function post(BankFee $bankFee, Request $request): JsonResponse
    {
        $this->authorize('post', $bankFee);

        return $this->idempotency->resolve($request, function () use ($request, $bankFee): JsonResponse {
            return (new BankFeeResource($this->service->post($bankFee, $request->user())))->response();
        });
    }

    public function reverse(ReverseBankFeeRequest $request, BankFee $bankFee): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $bankFee): JsonResponse {
            return (new BankFeeResource(
                $this->service->reverse($bankFee, $request->user(), $request->validated('reason'))
            ))->response();
        });
    }
}
