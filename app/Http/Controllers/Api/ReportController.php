<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Saldo seluruh Dana Amanah (posisi dana). */
    public function fundBalances(): JsonResponse
    {
        $rows = Fund::query()
            ->leftJoin('ledger_entries', 'ledger_entries.fund_id', '=', 'funds.id')
            ->groupBy('funds.id', 'funds.code', 'funds.name', 'funds.type', 'funds.is_system')
            ->select(
                'funds.id', 'funds.code', 'funds.name', 'funds.type', 'funds.is_system',
                DB::raw('COALESCE(SUM(ledger_entries.amount), 0) as balance')
            )
            ->orderBy('funds.name')
            ->get();

        return response()->json([
            'data' => $rows,
            'total' => (string) $rows->sum(fn ($r) => (float) $r->balance),
        ]);
    }

    /** Saldo seluruh Kas/Bank (posisi kas). */
    public function accountBalances(): JsonResponse
    {
        $rows = Account::query()
            ->leftJoin('ledger_entries', 'ledger_entries.account_id', '=', 'accounts.id')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->select(
                'accounts.id', 'accounts.code', 'accounts.name', 'accounts.type',
                DB::raw('COALESCE(SUM(ledger_entries.amount), 0) as balance')
            )
            ->orderBy('accounts.name')
            ->get();

        return response()->json([
            'data' => $rows,
            'total' => (string) $rows->sum(fn ($r) => (float) $r->balance),
        ]);
    }

    /**
     * Buku besar (ledger) — rincian mutasi.
     * Mendukung filter account_id, fund_id, type, rentang tanggal.
     */
    public function ledger(Request $request): JsonResponse
    {
        $entries = LedgerEntry::query()
            ->with(['account:id,code,name', 'fund:id,code,name', 'program:id,code,name'])
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('fund_id'), fn ($q) => $q->where('fund_id', $request->integer('fund_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date('to')))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50));

        return response()->json($entries);
    }

    /**
     * Mutasi per Dana Amanah dalam periode: total masuk, keluar, dan saldo akhir.
     */
    public function fundStatement(Request $request): JsonResponse
    {
        $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $fundId = $request->integer('fund_id');

        $query = LedgerEntry::where('fund_id', $fundId);
        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->date('to'));
        }

        $inflow = (clone $query)->where('amount', '>', 0)->sum('amount');
        $outflow = (clone $query)->where('amount', '<', 0)->sum('amount');

        return response()->json([
            'fund' => Fund::find($fundId),
            'inflow' => (string) $inflow,
            'outflow' => (string) abs((float) $outflow),
            'net' => (string) ((float) $inflow + (float) $outflow),
        ]);
    }
}
