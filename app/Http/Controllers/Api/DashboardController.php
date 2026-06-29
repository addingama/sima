<?php

namespace App\Http\Controllers\Api;

use App\Enums\DisbursementStatus;
use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\Receipt;
use App\Services\TrustFundBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly TrustFundBalanceService $balances) {}

    public function index(): JsonResponse
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return response()->json([
            'total_kas_bank' => $this->balances->totalAccountBalances(),
            'penerimaan_bulan_ini' => (string) Receipt::where('status', ReceiptStatus::APPROVED->value)
                ->whereBetween('receipt_date', [$monthStart, $monthEnd])
                ->sum('amount'),
            'pengeluaran_bulan_ini' => (string) Disbursement::where('status', DisbursementStatus::APPROVED->value)
                ->whereBetween('disbursement_date', [$monthStart, $monthEnd])
                ->sum('amount'),
            'pengeluaran_menunggu_approval' => Disbursement::whereIn('status', [
                DisbursementStatus::SUBMITTED->value,
                DisbursementStatus::VERIFIED->value,
            ])->count(),
            'jumlah_dana_amanah' => DB::table('funds')->whereNull('deleted_at')->count(),
        ]);
    }
}
