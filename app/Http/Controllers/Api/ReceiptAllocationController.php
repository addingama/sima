<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Services\AllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptAllocationController extends Controller
{
    public function __construct(private readonly AllocationService $service) {}

    /** Daftar alokasi untuk sebuah penerimaan. */
    public function index(Receipt $receipt): JsonResponse
    {
        return response()->json(
            $receipt->allocations()
                ->with(['fund:id,code,name', 'program:id,code,name'])
                ->orderByDesc('id')
                ->get()
        );
    }

    /** Membuat & memposting alokasi penerimaan ke Dana Amanah. */
    public function store(Request $request, Receipt $receipt): JsonResponse
    {
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $allocation = $this->service->allocate($receipt, $data, $request->user());

        return response()->json($allocation->load(['fund:id,code,name', 'program:id,code,name']), 201);
    }

    public function reverse(Request $request, ReceiptAllocation $allocation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->service->reverse($allocation, $request->user(), $data['reason']));
    }
}
