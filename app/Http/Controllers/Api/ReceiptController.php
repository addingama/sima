<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receipt\ApproveReceiptRequest;
use App\Http\Requests\Receipt\RejectReceiptRequest;
use App\Http\Requests\Receipt\ReverseReceiptRequest;
use App\Http\Requests\Receipt\StoreReceiptRequest;
use App\Http\Requests\Receipt\UpdateReceiptRequest;
use App\Http\Resources\ReceiptAllocationResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Services\IdempotencyService;
use App\Services\ReceiptService;
use App\Services\ReversalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $service,
        private readonly ReversalService $reversal,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Receipt::class);

        $receipts = Receipt::query()
            ->with(['account:id,code,name', 'donor:id,code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('donor_id'), fn ($q) => $q->where('donor_id', $request->integer('donor_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('receipt_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('receipt_date', '<=', $request->date('to')))
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ReceiptResource::collection($receipts)->response();
    }

    public function store(StoreReceiptRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $receipt = $this->service->create(
                $request->receiptData(),
                $request->allocations(),
                $request->user(),
            );

            return (new ReceiptResource($receipt->load('allocations.fund', 'allocations.program')))
                ->response()
                ->setStatusCode(201);
        });
    }

    public function show(Receipt $receipt): JsonResponse
    {
        $this->authorize('view', $receipt);

        return (new ReceiptResource($receipt->load([
            'account:id,code,name',
            'donor:id,code,name',
            'allocations.fund:id,code,name',
            'allocations.program:id,code,name',
            'approvals.actor:id,name',
            'attachments',
        ])))->response();
    }

    public function update(UpdateReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        $receipt = $this->service->update(
            $receipt,
            $request->receiptData(),
            $request->allocations(),
            $request->user(),
        );

        return (new ReceiptResource($receipt->load('allocations.fund', 'allocations.program')))->response();
    }

    public function allocations(Receipt $receipt): JsonResponse
    {
        $this->authorize('view', $receipt);

        return ReceiptAllocationResource::collection(
            $receipt->allocations()->with(['fund:id,code,name', 'program:id,code,name'])->get()
        )->response();
    }

    public function submit(Receipt $receipt, Request $request): JsonResponse
    {
        $this->authorize('submit', $receipt);

        return (new ReceiptResource($this->service->submit($receipt, $request->user())))->response();
    }

    public function approve(ApproveReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $receipt): JsonResponse {
            return (new ReceiptResource(
                $this->service->approve($receipt, $request->user(), $request->validated('notes'))
            ))->response();
        });
    }

    public function reject(RejectReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        return (new ReceiptResource(
            $this->service->reject($receipt, $request->user(), $request->validated('reason'))
        ))->response();
    }

    public function reverse(ReverseReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $receipt): JsonResponse {
            return (new ReceiptResource(
                $this->reversal->reverseReceipt($receipt, $request->user(), $request->validated('reason'))
            ))->response();
        });
    }
}
