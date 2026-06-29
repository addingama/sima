<?php

namespace App\Services;

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
            $systemBalance = $this->systemBalanceAsOf((int) $data['account_id'], (string) $data['period_end']);
            $difference = bcsub(bcadd((string) $data['statement_balance'], '0', 2), $systemBalance, 2);

            $reconciliation = BankReconciliation::create([
                'account_id' => $data['account_id'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'statement_balance' => $data['statement_balance'],
                'system_balance' => $systemBalance,
                'difference' => $difference,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log($reconciliation, 'created', null, $reconciliation->toArray(), $actor);

            return $reconciliation;
        });
    }

    /** @param array<string, mixed> $data */
    public function addLine(BankReconciliation $reconciliation, array $data): BankReconciliationLine
    {
        return $reconciliation->lines()->create($data);
    }

    public function complete(BankReconciliation $reconciliation, User $actor): BankReconciliation
    {
        // Hitung ulang saldo sistem agar selisih akhir akurat (tanpa menyentuh ledger).
        $systemBalance = $this->systemBalanceAsOf($reconciliation->account_id, $reconciliation->period_end->toDateString());
        $difference = bcsub((string) $reconciliation->statement_balance, $systemBalance, 2);

        $reconciliation->update([
            'system_balance' => $systemBalance,
            'difference' => $difference,
            'status' => 'completed',
            'reconciled_at' => now(),
            'reconciled_by' => $actor->getKey(),
        ]);

        $this->audit->log($reconciliation, 'completed', null, [
            'system_balance' => $systemBalance,
            'difference' => $difference,
        ], $actor);

        return $reconciliation->refresh();
    }
}
