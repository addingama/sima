<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankFee;
use App\Services\BankFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankFeeController extends Controller
{
    public function __construct(private readonly BankFeeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = BankFee::query()
            ->with(['account:id,code,name', 'fund:id,code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->orderByDesc('fee_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fee_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'fund_id' => ['nullable', 'exists:funds,id'],
            'fee_type' => ['required', 'in:admin,transfer,tax,other'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
        ]);

        $fee = $this->service->create($data, $request->user());

        return response()->json($fee, 201);
    }

    public function show(BankFee $bankFee): JsonResponse
    {
        return response()->json($bankFee->load(['account:id,code,name', 'fund:id,code,name']));
    }

    public function post(BankFee $bankFee, Request $request): JsonResponse
    {
        return response()->json($this->service->post($bankFee, $request->user()));
    }

    public function reverse(BankFee $bankFee, Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->service->reverse($bankFee, $request->user(), $data['reason']));
    }
}
