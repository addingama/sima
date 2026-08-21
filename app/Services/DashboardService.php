<?php

namespace App\Services;

use App\Domains\Ledger\Services\BalanceService;
use App\Enums\DisbursementStatus;
use App\Enums\ReceiptStatus;
use App\Models\Disbursement;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
            'penerimaan_bulan_ini' => bcadd((string) Receipt::where('status', ReceiptStatus::APPROVED->value)
                ->whereDate('receipt_date', '>=', $monthStart)
                ->whereDate('receipt_date', '<=', $monthEnd)
                ->sum('amount'), '0', 2),
            'pengeluaran_bulan_ini' => bcadd((string) Disbursement::where('status', DisbursementStatus::APPROVED->value)
                ->whereDate('disbursement_date', '>=', $monthStart)
                ->whereDate('disbursement_date', '<=', $monthEnd)
                ->sum('amount'), '0', 2),
            'receipts_pending' => Receipt::whereIn('status', [
                ReceiptStatus::DRAFT->value,
                ReceiptStatus::SUBMITTED->value,
            ])->count(),
            'disbursements_pending' => Disbursement::whereIn('status', [
                DisbursementStatus::SUBMITTED->value,
                DisbursementStatus::VERIFIED->value,
            ])->count(),
            'total_dana_amanah' => $this->balances->totalFundBalances(),
            'cash_flow_monthly' => $this->cashFlowMonthly(6),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Aggregasi penerimaan/pengeluaran approved per bulan (termasuk bulan kosong = 0).
     *
     * @return list<array{month: string, label: string, penerimaan: string, pengeluaran: string}>
     */
    public function cashFlowMonthly(int $months = 6): array
    {
        $months = max(1, min($months, 24));
        $start = now()->startOfMonth()->subMonths($months - 1);
        $end = now()->endOfMonth();

        $receiptExpr = $this->yearMonthExpression('receipt_date');
        $expenseExpr = $this->yearMonthExpression('disbursement_date');

        $receiptsByMonth = Receipt::query()
            ->selectRaw("{$receiptExpr} as ym, SUM(amount) as total")
            ->where('status', ReceiptStatus::APPROVED->value)
            ->whereDate('receipt_date', '>=', $start->toDateString())
            ->whereDate('receipt_date', '<=', $end->toDateString())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $expensesByMonth = Disbursement::query()
            ->selectRaw("{$expenseExpr} as ym, SUM(amount) as total")
            ->where('status', DisbursementStatus::APPROVED->value)
            ->whereDate('disbursement_date', '>=', $start->toDateString())
            ->whereDate('disbursement_date', '<=', $end->toDateString())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            /** @var Carbon $cursor */
            $cursor = $start->copy()->addMonths($i);
            $ym = $cursor->format('Y-m');
            $series[] = [
                'month' => $ym,
                'label' => $cursor->locale('id')->translatedFormat('M Y'),
                'penerimaan' => bcadd((string) ($receiptsByMonth[$ym] ?? '0'), '0', 2),
                'pengeluaran' => bcadd((string) ($expensesByMonth[$ym] ?? '0'), '0', 2),
            ];
        }

        return $series;
    }

    private function yearMonthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
