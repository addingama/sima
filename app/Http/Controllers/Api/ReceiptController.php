<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $service) {}

    public function index(Request $request): JsonResponse
    {
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

        return response()->json($receipts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receipt_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'donor_id' => ['nullable', 'exists:donors,id'],
            'channel' => ['required', 'in:cash,transfer,qris,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
        ]);

        $receipt = $this->service->create($data, $request->user());

        return response()->json($receipt, 201);
    }

    public function show(Receipt $receipt): JsonResponse
    {
        $receipt->load([
            'account:id,code,name',
            'donor:id,code,name',
            'allocations.fund:id,code,name',
            'allocations.program:id,code,name',
            'approvals.actor:id,name',
        ]);
        $receipt->allocated_amount = $receipt->allocatedAmount();
        $receipt->unallocated_amount = $receipt->unallocatedAmount();

        return response()->json($receipt);
    }

    public function post(Receipt $receipt, Request $request): JsonResponse
    {
        return response()->json($this->service->post($receipt, $request->user()));
    }

    public function reverse(Receipt $receipt, Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->service->reverse($receipt, $request->user(), $data['reason']));
    }
}
