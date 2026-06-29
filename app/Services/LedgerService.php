<?php

namespace App\Services;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerMovement;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\BankFee;
use App\Models\Disbursement;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Amanah Ledger — mesin keuangan inti (double-entry, ACID).
 *
 * - Source of truth: ledger_entries (debit/credit per ledger account).
 * - Saldo TIDAK disimpan statis; dihitung dari agregasi ledger.
 * - Semua transaksi finansial wajib melalui service ini.
 * - Pembatalan hanya via reversal (entri lawan), bukan update/delete.
 */
class LedgerService
{
    /** Saldo kas/bank = SUM(debit) − SUM(credit). */
    public function balanceForAccount(int $accountId): string
    {
        return $this->balanceFor(LedgerAccountType::ACCOUNT, $accountId);
    }

    /** Saldo Dana Amanah = SUM(credit) − SUM(debit). */
    public function balanceForFund(int $fundId): string
    {
        return $this->balanceFor(LedgerAccountType::FUND, $fundId);
    }

    public function balanceFor(LedgerAccountType $type, int $id): string
    {
        return $this->normalize($this->rawBalanceFor($type, $id));
    }

    /** Saldo kas/bank s/d tanggal (inklusif). */
    public function accountBalanceAsOf(int $accountId, string $asOfDate): string
    {
        return $this->normalize($this->rawBalanceFor(
            LedgerAccountType::ACCOUNT,
            $accountId,
            $asOfDate
        ));
    }

    /**
     * Posting jurnal seimbang (total debit = total credit).
     *
     * @param  array<int, array{ledger_account_type: LedgerAccountType|string, ledger_account_id: int, debit: string|float, credit: string|float}>  $lines
     * @return Collection<int, LedgerEntry>
     */
    public function postJournal(
        TransactionType $transactionType,
        int $transactionId,
        array $lines,
        ?string $reference = null,
    ): Collection {
        $normalized = $this->normalizeLines($lines);
        $this->assertJournalBalanced($normalized);

        return DB::transaction(function () use ($transactionType, $transactionId, $normalized, $reference): Collection {
            $this->lockLedgerAccounts($normalized);
            $this->assertSufficientForOutflows($normalized);

            $now = now();
            $entries = collect();

            foreach ($normalized as $line) {
                $entries->push(LedgerEntry::create([
                    'transaction_type' => $transactionType->value,
                    'transaction_id' => $transactionId,
                    'ledger_account_type' => $line['ledger_account_type']->value,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'reference' => $reference,
                    'created_at' => $now,
                ]));
            }

            return $entries;
        });
    }

    /**
     * Pasangan arus Amanah: kas/bank ↔ Dana Amanah (satu nominal, dua entri).
     *
     * @param  array<int, array{fund_id: int, amount: string|float}>  $fundLines
     * @return Collection<int, LedgerEntry>
     */
    public function postAmanahMovement(
        TransactionType $transactionType,
        int $transactionId,
        int $accountId,
        array $fundLines,
        LedgerMovement $movement,
        ?string $reference = null,
    ): Collection {
        $lines = [];

        foreach ($fundLines as $row) {
            $amount = bcadd((string) $row['amount'], '0', 2);
            $fundId = (int) $row['fund_id'];

            if ($movement === LedgerMovement::IN) {
                $lines[] = $this->line(LedgerAccountType::ACCOUNT, $accountId, $amount, '0');
                $lines[] = $this->line(LedgerAccountType::FUND, $fundId, '0', $amount);
            } else {
                $lines[] = $this->line(LedgerAccountType::ACCOUNT, $accountId, '0', $amount);
                $lines[] = $this->line(LedgerAccountType::FUND, $fundId, $amount, '0');
            }
        }

        return $this->postJournal($transactionType, $transactionId, $lines, $reference);
    }

    /**
     * Reversal: entri lawan (swap debit/credit) untuk transaksi sumber.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function reverse(
        TransactionType $originalType,
        int $originalTransactionId,
        int $reversalTransactionId,
        ?string $reference = null,
    ): Collection {
        $original = LedgerEntry::query()
            ->where('transaction_type', $originalType->value)
            ->where('transaction_id', $originalTransactionId)
            ->get();

        if ($original->isEmpty()) {
            return collect();
        }

        if ($this->isReversed($originalType, $originalTransactionId)) {
            return collect();
        }

        $lines = $original->map(fn (LedgerEntry $e) => [
            'ledger_account_type' => $e->ledger_account_type,
            'ledger_account_id' => $e->ledger_account_id,
            'debit' => bcadd((string) $e->credit, '0', 2),
            'credit' => bcadd((string) $e->debit, '0', 2),
        ])->all();

        $ref = $reference ?? $this->reversalReference($originalType, $originalTransactionId);

        return $this->postJournal(
            TransactionType::REVERSAL,
            $reversalTransactionId,
            $lines,
            $ref
        );
    }

    public function isReversed(TransactionType $originalType, int $originalTransactionId): bool
    {
        return LedgerEntry::query()
            ->where('transaction_type', TransactionType::REVERSAL->value)
            ->where('reference', $this->reversalReference($originalType, $originalTransactionId))
            ->exists();
    }

    /** Total debit global (harus = total credit). */
    public function totalDebits(): string
    {
        return $this->normalize((string) (LedgerEntry::sum('debit') ?? '0'));
    }

    /** Total credit global. */
    public function totalCredits(): string
    {
        return $this->normalize((string) (LedgerEntry::sum('credit') ?? '0'));
    }

    /** @deprecated Alias backward-compat — gunakan balanceForAccount(). */
    public function ledgerSumForAccount(int $accountId): string
    {
        return $this->balanceForAccount($accountId);
    }

    /** @deprecated Alias backward-compat — gunakan balanceForFund(). */
    public function ledgerSumForFund(int $fundId): string
    {
        return $this->balanceForFund($fundId);
    }

    /** @deprecated Gunakan postAmanahMovement() atau postJournal(). */
    public function post(array $legs, $actor = null): Collection
    {
        if ($legs === []) {
            return collect();
        }

        $first = $legs[0];
        $type = $first['type'] ?? TransactionType::ADJUSTMENT;
        $transactionType = $type instanceof TransactionType
            ? $type
            : TransactionType::tryFrom((string) $type) ?? TransactionType::ADJUSTMENT;

        $source = $first['source'] ?? null;
        $transactionId = $source?->getKey() ?? 0;
        $reference = $first['memo'] ?? null;

        $fundLines = array_map(fn (array $leg) => [
            'fund_id' => (int) $leg['fund_id'],
            'amount' => bcadd((string) $leg['amount'], '0', 2),
        ], $legs);

        $movement = bccomp((string) ($fundLines[0]['amount'] ?? '0'), '0', 2) >= 0
            ? LedgerMovement::IN
            : LedgerMovement::OUT;

        if ($movement === LedgerMovement::OUT) {
            $fundLines = array_map(fn (array $row) => [
                'fund_id' => $row['fund_id'],
                'amount' => ltrim($row['amount'], '-'),
            ], $fundLines);
        }

        return $this->postAmanahMovement(
            $transactionType,
            (int) $transactionId,
            (int) $first['account_id'],
            $fundLines,
            $movement,
            $reference
        );
    }

    /** @deprecated Gunakan reverse(). */
    public function reverseModel(Model $source, $actor = null, ?string $memo = null): Collection
    {
        $type = match ($source::class) {
            Receipt::class => TransactionType::RECEIPT,
            Disbursement::class => TransactionType::EXPENSE,
            BankFee::class => TransactionType::BANK_FEE,
            default => TransactionType::ADJUSTMENT,
        };

        return $this->reverse($type, (int) $source->getKey(), (int) $source->getKey(), $memo);
    }

    private function rawBalanceFor(LedgerAccountType $type, int $id, ?string $asOfDate = null): string
    {
        $query = LedgerEntry::query()
            ->where('ledger_account_type', $type->value)
            ->where('ledger_account_id', $id);

        if ($asOfDate !== null) {
            $query->whereDate('created_at', '<=', $asOfDate);
        }

        if ($type === LedgerAccountType::ACCOUNT) {
            $debit = (string) ($query->clone()->sum('debit') ?? '0');
            $credit = (string) ($query->clone()->sum('credit') ?? '0');

            return bcsub($debit, $credit, 2);
        }

        $debit = (string) ($query->clone()->sum('debit') ?? '0');
        $credit = (string) ($query->clone()->sum('credit') ?? '0');

        return bcsub($credit, $debit, 2);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function normalizeLines(array $lines): array
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

    /** @param array<int, array<string, mixed>> $lines */
    private function assertJournalBalanced(array $lines): void
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
    private function lockLedgerAccounts(array $lines): void
    {
        $fundIds = [];
        $accountIds = [];

        foreach ($lines as $line) {
            if ($line['ledger_account_type'] === LedgerAccountType::FUND) {
                $fundIds[$line['ledger_account_id']] = true;
            } else {
                $accountIds[$line['ledger_account_id']] = true;
            }
        }

        ksort($fundIds);
        foreach (array_keys($fundIds) as $fundId) {
            Fund::query()->whereKey($fundId)->lockForUpdate()->first();
        }

        ksort($accountIds);
        foreach (array_keys($accountIds) as $accountId) {
            Account::query()->whereKey($accountId)->lockForUpdate()->first();
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function assertSufficientForOutflows(array $lines): void
    {
        $accountOut = [];
        $fundOut = [];

        foreach ($lines as $line) {
            if ($line['ledger_account_type'] === LedgerAccountType::ACCOUNT && bccomp($line['credit'], '0', 2) === 1) {
                $id = $line['ledger_account_id'];
                $accountOut[$id] = bcadd($accountOut[$id] ?? '0.00', $line['credit'], 2);
            }

            if ($line['ledger_account_type'] === LedgerAccountType::FUND && bccomp($line['debit'], '0', 2) === 1) {
                $id = $line['ledger_account_id'];
                $fundOut[$id] = bcadd($fundOut[$id] ?? '0.00', $line['debit'], 2);
            }
        }

        foreach ($accountOut as $accountId => $needed) {
            $balance = $this->balanceForAccount((int) $accountId);
            if (bccomp($balance, $needed, 2) < 0) {
                throw InsufficientBalanceException::account(
                    Account::find($accountId)?->name ?? "#{$accountId}",
                    $balance,
                    $needed
                );
            }
        }

        foreach ($fundOut as $fundId => $needed) {
            $balance = $this->balanceForFund((int) $fundId);
            if (bccomp($balance, $needed, 2) < 0) {
                throw InsufficientBalanceException::fund(
                    Fund::find($fundId)?->name ?? "#{$fundId}",
                    $balance,
                    $needed
                );
            }
        }
    }

    private function line(LedgerAccountType $type, int $id, string $debit, string $credit): array
    {
        return [
            'ledger_account_type' => $type,
            'ledger_account_id' => $id,
            'debit' => bcadd($debit, '0', 2),
            'credit' => bcadd($credit, '0', 2),
        ];
    }

    private function reversalReference(TransactionType $type, int $id): string
    {
        return "reversal:{$type->value}:{$id}";
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
