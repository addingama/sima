<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = BankReconciliation::query()
            ->with('account:id,code,name')
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('period_end')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'statement_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $reconciliation = $this->service->create($data, $request->user());

        return response()->json($reconciliation->load('account:id,code,name'), 201);
    }

    public function show(BankReconciliation $bankReconciliation): JsonResponse
    {
        $bankReconciliation->load([
            'account:id,code,name',
            'lines.ledgerEntry:id,entry_date,amount,type,memo',
        ]);

        $reconciling = $this->service->deferredBankFeeItems(
            $bankReconciliation->account_id,
            $bankReconciliation->period_end->toDateString()
        );

        return response()->json([
            ...$bankReconciliation->toArray(),
            'reconciling_items' => $reconciling['items'],
            'reconciling_total' => $reconciling['total'],
            'adjusted_difference' => $this->service->adjustedDifference(
                (string) $bankReconciliation->difference,
                $reconciling['total']
            ),
        ]);
    }

    public function addLine(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        $data = $request->validate([
            'ledger_entry_id' => ['nullable', 'exists:ledger_entries,id'],
            'statement_date' => ['nullable', 'date'],
            'statement_ref' => ['nullable', 'string', 'max:255'],
            'statement_amount' => ['nullable', 'numeric'],
            'is_matched' => ['boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $line = $this->service->addLine($bankReconciliation, $data);

        return response()->json($line, 201);
    }

    public function complete(BankReconciliation $bankReconciliation, Request $request): JsonResponse
    {
        return response()->json($this->service->complete($bankReconciliation, $request->user()));
    }
}
