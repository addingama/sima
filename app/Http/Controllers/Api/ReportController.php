<?php

namespace App\Http\Controllers\Api;

use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use App\Services\TrustFundBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly TrustFundBalanceService $balances,
    ) {}

    /** Saldo seluruh Dana Amanah (posisi dana) — dihitung dari ledger. */
    public function fundBalances(): JsonResponse
    {
        $rows = Fund::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'is_system'])
            ->map(fn (Fund $fund) => [
                'id' => $fund->id,
                'code' => $fund->code,
                'name' => $fund->name,
                'type' => $fund->type,
                'is_system' => $fund->is_system,
                'balance' => $this->ledger->balanceForFund($fund->id),
            ]);

        return response()->json([
            'data' => $rows,
            'total' => $rows->reduce(fn (string $c, $r) => bcadd($c, $r['balance'], 2), '0.00'),
        ]);
    }

    /** Saldo seluruh Kas/Bank (posisi kas) — dihitung dari ledger. */
    public function accountBalances(): JsonResponse
    {
        $rows = Account::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $this->ledger->balanceForAccount($account->id),
            ]);

        return response()->json([
            'data' => $rows,
            'total' => $rows->reduce(fn (string $c, $r) => bcadd($c, $r['balance'], 2), '0.00'),
        ]);
    }

    /** Buku besar (ledger) — rincian mutasi double-entry. */
    public function ledger(Request $request): JsonResponse
    {
        $entries = LedgerEntry::query()
            ->when($request->filled('ledger_account_type'), fn ($q) => $q->where(
                'ledger_account_type',
                $request->string('ledger_account_type')
            ))
            ->when($request->filled('ledger_account_id'), fn ($q) => $q->where(
                'ledger_account_id',
                $request->integer('ledger_account_id')
            ))
            ->when($request->filled('account_id'), fn ($q) => $q
                ->where('ledger_account_type', LedgerAccountType::ACCOUNT->value)
                ->where('ledger_account_id', $request->integer('account_id')))
            ->when($request->filled('fund_id'), fn ($q) => $q
                ->where('ledger_account_type', LedgerAccountType::FUND->value)
                ->where('ledger_account_id', $request->integer('fund_id')))
            ->when($request->filled('transaction_type'), fn ($q) => $q->where(
                'transaction_type',
                $request->string('transaction_type')
            ))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50));

        return response()->json($entries);
    }

    /**
     * Rekonsiliasi global Amanah: total kas = total dana; debit = credit.
     */
    public function reconciliationSummary(): JsonResponse
    {
        $totalAccounts = $this->balances->totalAccountBalances();
        $totalFunds = $this->balances->totalFundBalances();
        $totalDebits = $this->ledger->totalDebits();
        $totalCredits = $this->ledger->totalCredits();

        return response()->json([
            'total_kas_bank' => $totalAccounts,
            'total_dana_amanah' => $totalFunds,
            'total_debit' => $totalDebits,
            'total_credit' => $totalCredits,
            'selisih_kas_dana' => bcsub($totalAccounts, $totalFunds, 2),
            'selisih_debit_credit' => bcsub($totalDebits, $totalCredits, 2),
            'seimbang' => bccomp($totalAccounts, $totalFunds, 2) === 0
                && bccomp($totalDebits, $totalCredits, 2) === 0,
        ]);
    }

    /** Mutasi per Dana Amanah dalam periode. */
    public function fundStatement(Request $request): JsonResponse
    {
        $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $fundId = $request->integer('fund_id');
        $query = LedgerEntry::query()
            ->where('ledger_account_type', LedgerAccountType::FUND->value)
            ->where('ledger_account_id', $fundId);

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        $credit = (string) ($query->clone()->sum('credit') ?? '0');
        $debit = (string) ($query->clone()->sum('debit') ?? '0');
        $creditStr = bcadd($credit, '0', 2);
        $debitStr = bcadd($debit, '0', 2);

        return response()->json([
            'fund' => Fund::find($fundId),
            'inflow' => $creditStr,
            'outflow' => $debitStr,
            'net' => bcsub($creditStr, $debitStr, 2),
        ]);
    }
}
