<?php

namespace App\Domains\Expense\Validators;

use App\Domains\Ledger\Services\BalanceService;
use App\Enums\DisbursementStatus;
use App\Exceptions\DomainException;
use App\Models\Disbursement;

class ExpenseValidator
{
    public function __construct(private readonly BalanceService $balances) {}

    /** @param array<int, array<string, mixed>> $sources */
    public function assertSourcesMatch(string $amount, array $sources): void
    {
        if (count($sources) === 0) {
            throw new DomainException('Pengeluaran harus memiliki minimal satu sumber Dana Amanah.');
        }

        $total = '0.00';
        foreach ($sources as $s) {
            if (bccomp((string) $s['amount'], '0', 2) <= 0) {
                throw new DomainException('Nominal tiap sumber dana harus lebih besar dari nol.');
            }
            $total = bcadd($total, (string) $s['amount'], 2);
        }

        if (bccomp($total, $amount, 2) !== 0) {
            throw new DomainException(
                "Total sumber dana ({$total}) harus sama dengan total pengeluaran ({$amount})."
            );
        }
    }

    /** @param array<int, DisbursementStatus> $allowed */
    public function assertStatus(Disbursement $expense, array $allowed): void
    {
        if (! in_array($expense->status, $allowed, true)) {
            $allowedLabels = implode(', ', array_map(fn (DisbursementStatus $s) => $s->value, $allowed));
            throw new DomainException(
                "Aksi tidak valid untuk status \"{$expense->status->value}\". Status diizinkan: {$allowedLabels}."
            );
        }
    }

    public function assertFundsAvailable(Disbursement $expense): void
    {
        $expense->loadMissing('fundSources');

        $perFund = [];
        foreach ($expense->fundSources as $source) {
            $perFund[$source->fund_id] = bcadd($perFund[$source->fund_id] ?? '0.00', (string) $source->amount, 2);
        }

        foreach ($perFund as $fundId => $needed) {
            $this->balances->assertFundSufficient((int) $fundId, $needed);
        }

        $this->balances->assertAccountSufficient($expense->account_id, bcadd((string) $expense->amount, '0', 2));
    }

    public function assertApprovedForReversal(Disbursement $expense): void
    {
        if ($expense->status !== DisbursementStatus::APPROVED) {
            throw new DomainException('Hanya pengeluaran yang sudah approved yang dapat dibatalkan (reversal).');
        }
    }
}
