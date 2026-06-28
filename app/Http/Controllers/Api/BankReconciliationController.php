<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
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

        // Snapshot saldo sistem berdasarkan ledger s/d akhir periode.
        $systemBalance = (string) (LedgerEntry::where('account_id', $data['account_id'])
            ->whereDate('entry_date', '<=', $data['period_end'])
            ->sum('amount') ?? 0);

        $reconciliation = BankReconciliation::create([
            ...$data,
            'system_balance' => $systemBalance,
            'difference' => bcsub((string) $data['statement_balance'], $systemBalance, 2),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return response()->json($reconciliation->load('account:id,code,name'), 201);
    }

    public function show(BankReconciliation $bankReconciliation): JsonResponse
    {
        return response()->json($bankReconciliation->load([
            'account:id,code,name',
            'lines.ledgerEntry:id,entry_date,amount,type,memo',
        ]));
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

        $line = $bankReconciliation->lines()->create($data);

        return response()->json($line, 201);
    }

    public function complete(BankReconciliation $bankReconciliation): JsonResponse
    {
        $bankReconciliation->update([
            'status' => 'completed',
            'reconciled_at' => now(),
            'reconciled_by' => request()->user()->id,
        ]);

        return response()->json($bankReconciliation);
    }
}
