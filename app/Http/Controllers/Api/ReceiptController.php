<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\ReceiptService;
use App\Services\ReversalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $service,
        private readonly ReversalService $reversal,
    ) {}

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
        $validated = $request->validate([
            'receipt_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'donor_id' => ['nullable', 'exists:donors,id'],
            'channel' => ['required', 'in:cash,transfer,qris,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.fund_id' => ['required', 'exists:funds,id'],
            'allocations.*.program_id' => ['nullable', 'exists:programs,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
            'allocations.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $allocations = $validated['allocations'];
        unset($validated['allocations']);

        $receipt = $this->service->create($validated, $allocations, $request->user());

        return response()->json($receipt, 201);
    }

    public function show(Receipt $receipt): JsonResponse
    {
        return response()->json($receipt->load([
            'account:id,code,name',
            'donor:id,code,name',
            'allocations.fund:id,code,name',
            'allocations.program:id,code,name',
            'approvals.actor:id,name',
            'attachments',
        ]));
    }

    public function update(Request $request, Receipt $receipt): JsonResponse
    {
        $validated = $request->validate([
            'receipt_date' => ['sometimes', 'date'],
            'account_id' => ['sometimes', 'exists:accounts,id'],
            'donor_id' => ['nullable', 'exists:donors,id'],
            'channel' => ['sometimes', 'in:cash,transfer,qris,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'allocations' => ['sometimes', 'array', 'min:1'],
            'allocations.*.fund_id' => ['required_with:allocations', 'exists:funds,id'],
            'allocations.*.program_id' => ['nullable', 'exists:programs,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0'],
            'allocations.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $allocations = $validated['allocations'] ?? null;
        unset($validated['allocations']);

        $receipt = $this->service->update($receipt, $validated, $allocations, $request->user());

        return response()->json($receipt);
    }

    public function allocations(Receipt $receipt): JsonResponse
    {
        return response()->json(
            $receipt->allocations()->with(['fund:id,code,name', 'program:id,code,name'])->get()
        );
    }

    public function submit(Receipt $receipt, Request $request): JsonResponse
    {
        return response()->json($this->service->submit($receipt, $request->user()));
    }

    public function approve(Receipt $receipt, Request $request): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        return response()->json($this->service->approve($receipt, $request->user(), $data['notes'] ?? null));
    }

    public function reject(Receipt $receipt, Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->service->reject($receipt, $request->user(), $data['reason']));
    }

    public function reverse(Receipt $receipt, Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->reversal->reverseReceipt($receipt, $request->user(), $data['reason']));
    }
}
