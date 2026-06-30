<?php

namespace App\Domains\Ledger\Validators;

use App\Enums\LedgerAccountType;

class JournalValidator
{
    /** @param array<int, array<string, mixed>> $lines */
    public function assertJournalBalanced(array $lines): void
    {
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($lines as $line) {
            $totalDebit = bcadd($totalDebit, $line['debit'], 2);
            $totalCredit = bcadd($totalCredit, $line['credit'], 2);
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw new \InvalidArgumentException(
                "Jurnal tidak seimbang: debit={$totalDebit}, credit={$totalCredit}."
            );
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $type = $line['ledger_account_type'] instanceof LedgerAccountType
                ? $line['ledger_account_type']
                : LedgerAccountType::from((string) $line['ledger_account_type']);

            $debit = bcadd((string) ($line['debit'] ?? '0'), '0', 2);
            $credit = bcadd((string) ($line['credit'] ?? '0'), '0', 2);

            if (bccomp($debit, '0', 2) === 0 && bccomp($credit, '0', 2) === 0) {
                throw new \InvalidArgumentException('Setiap baris ledger wajib memiliki debit atau credit.');
            }

            if (bccomp($debit, '0', 2) === 1 && bccomp($credit, '0', 2) === 1) {
                throw new \InvalidArgumentException('Baris ledger tidak boleh memiliki debit dan credit sekaligus.');
            }

            $normalized[] = [
                'ledger_account_type' => $type,
                'ledger_account_id' => (int) $line['ledger_account_id'],
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        return $normalized;
    }
}
