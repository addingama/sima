<?php

namespace App\Services;

use App\Domains\Ledger\Services\BalanceService;
use App\Enums\DisbursementStatus;
use App\Enums\ReceiptStatus;
use App\Models\Disbursement;
use App\Models\Receipt;

class DashboardService
{
    public function __construct(private readonly BalanceService $balances) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return [
            'total_kas_bank' => $this->balances->totalAccountBalances(),
            'penerimaan_bulan_ini' => (string) Receipt::where('status', ReceiptStatus::APPROVED->value)
                ->whereDate('receipt_date', '>=', $monthStart)
                ->whereDate('receipt_date', '<=', $monthEnd)
                ->sum('amount'),
            'pengeluaran_bulan_ini' => (string) Disbursement::where('status', DisbursementStatus::APPROVED->value)
                ->whereDate('disbursement_date', '>=', $monthStart)
                ->whereDate('disbursement_date', '<=', $monthEnd)
                ->sum('amount'),
            'receipts_pending' => Receipt::whereIn('status', [
                ReceiptStatus::DRAFT->value,
                ReceiptStatus::SUBMITTED->value,
            ])->count(),
            'disbursements_pending' => Disbursement::whereIn('status', [
                DisbursementStatus::SUBMITTED->value,
                DisbursementStatus::VERIFIED->value,
            ])->count(),
            'total_dana_amanah' => $this->balances->totalFundBalances(),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
