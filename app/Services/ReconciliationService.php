<?php

namespace App\Services;

use App\Enums\BankFeeStatus;
use App\Models\BankFee;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi Bank: mencocokkan saldo rekening koran dengan saldo ledger kas/bank.
 *
 * PENTING: Rekonsiliasi TIDAK PERNAH mengubah ledger. Ia hanya menghitung selisih
 * dan menyimpan hasil pencocokan. Koreksi nilai dilakukan via transaksi/ reversal terpisah.
 */
class ReconciliationService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Saldo sistem (ledger) untuk sebuah akun s/d tanggal tertentu.
     */
    public function systemBalanceAsOf(int $accountId, string $asOfDate): string
    {
        return bcadd((string) (LedgerEntry::where('account_id', $accountId)
            ->whereDate('entry_date', '<=', $asOfDate)
            ->sum('amount') ?? '0'), '0', 2);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($data, $actor): BankReconciliation {
            $accountId = (int) $data['account_id'];
            $periodEnd = (string) $data['period_end'];
            $systemBalance = $this->systemBalanceAsOf($accountId, $periodEnd);
            $difference = bcsub(bcadd((string) $data['statement_balance'], '0', 2), $systemBalance, 2);
            $reconciling = $this->deferredBankFeeItems($accountId, $periodEnd);
            $adjustedDifference = bcadd($difference, $reconciling['total'], 2);

            $reconciliation = BankReconciliation::create([
                'account_id' => $accountId,
                'period_start' => $data['period_start'],
                'period_end' => $periodEnd,
                'statement_balance' => $data['statement_balance'],
                'system_balance' => $systemBalance,
                'difference' => $difference,
                'status' => 'draft',
                'notes' => $this->appendReconcilingNotes($data['notes'] ?? null, $reconciling, $adjustedDifference),
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log($reconciliation, 'created', null, [
                ...$reconciliation->toArray(),
                'reconciling_items' => $reconciling['items'],
                'adjusted_difference' => $adjustedDifference,
            ], $actor);

            return $reconciliation;
        });
    }

    /**
     * Item penjelas selisih: biaya bank deferred (kas fisik sudah keluar, ledger belum diposting).
     * Opsi (a): tampilkan sebagai reconciling item, bukan ubah ledger.
     *
     * @return array{items: array<int, array<string, mixed>>, total: string}
     */
    public function deferredBankFeeItems(int $accountId, string $asOfDate): array
    {
        $fees = BankFee::query()
            ->where('account_id', $accountId)
            ->where('status', BankFeeStatus::DEFERRED->value)
            ->whereDate('fee_date', '<=', $asOfDate)
            ->with('operationalLiability:id,liability_number,amount,status')
            ->get();

        $items = [];
        $total = '0.00';

        foreach ($fees as $fee) {
            $amount = bcadd((string) $fee->amount, '0', 2);
            $total = bcadd($total, $amount, 2);
            $items[] = [
                'type' => 'deferred_bank_fee',
                'bank_fee_id' => $fee->id,
                'fee_number' => $fee->fee_number,
                'fee_date' => $fee->fee_date->toDateString(),
                'amount' => $amount,
                'operational_liability_id' => $fee->operational_liability_id,
                'liability_number' => $fee->operationalLiability?->liability_number,
                'description' => 'Biaya bank tertunda — kas fisik sudah keluar, menunggu Dana Operasional',
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /** Selisih setelah dijelaskan item reconciling (target mendekati 0). */
    public function adjustedDifference(string $rawDifference, string $reconcilingTotal): string
    {
        return bcadd($rawDifference, $reconcilingTotal, 2);
    }

    private function appendReconcilingNotes(?string $notes, array $reconciling, string $adjustedDifference): ?string
    {
        if (bccomp($reconciling['total'], '0', 2) === 0) {
            return $notes;
        }

        $summary = sprintf(
            '[Reconciling] Biaya bank deferred: %s (%d item). Selisih disesuaikan: %s.',
            $reconciling['total'],
            count($reconciling['items']),
            $adjustedDifference
        );

        return $notes ? "{$notes}\n{$summary}" : $summary;
    }

    /** @param array<string, mixed> $data */
    public function addLine(BankReconciliation $reconciliation, array $data): BankReconciliationLine
    {
        return $reconciliation->lines()->create($data);
    }

    public function complete(BankReconciliation $reconciliation, User $actor): BankReconciliation
    {
        $periodEnd = $reconciliation->period_end->toDateString();
        $systemBalance = $this->systemBalanceAsOf($reconciliation->account_id, $periodEnd);
        $difference = bcsub((string) $reconciliation->statement_balance, $systemBalance, 2);
        $reconciling = $this->deferredBankFeeItems($reconciliation->account_id, $periodEnd);
        $adjustedDifference = $this->adjustedDifference($difference, $reconciling['total']);

        $reconciliation->update([
            'system_balance' => $systemBalance,
            'difference' => $difference,
            'status' => 'completed',
            'reconciled_at' => now(),
            'reconciled_by' => $actor->getKey(),
            'notes' => $this->appendReconcilingNotes($reconciliation->notes, $reconciling, $adjustedDifference),
        ]);

        $this->audit->log($reconciliation, 'completed', null, [
            'system_balance' => $systemBalance,
            'difference' => $difference,
            'reconciling_items' => $reconciling['items'],
            'adjusted_difference' => $adjustedDifference,
        ], $actor);

        return $reconciliation->refresh();
    }
}
