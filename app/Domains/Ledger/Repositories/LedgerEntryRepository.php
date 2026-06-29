<?php

namespace App\Domains\Ledger\Repositories;

use App\Enums\LedgerAccountType;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use Illuminate\Support\Collection;

class LedgerEntryRepository
{
    public function create(array $attributes): LedgerEntry
    {
        return LedgerEntry::create($attributes);
    }

    /** @return Collection<int, LedgerEntry> */
    public function findByTransaction(TransactionType $type, int $transactionId): Collection
    {
        return LedgerEntry::query()
            ->where('transaction_type', $type->value)
            ->where('transaction_id', $transactionId)
            ->get();
    }

    public function reversalExists(string $reference): bool
    {
        return LedgerEntry::query()
            ->where('transaction_type', TransactionType::REVERSAL->value)
            ->where('reference', $reference)
            ->exists();
    }

    public function rawBalanceFor(LedgerAccountType $type, int $id, ?string $asOfDate = null): string
    {
        $query = LedgerEntry::query()
            ->where('ledger_account_type', $type->value)
            ->where('ledger_account_id', $id);

        if ($asOfDate !== null) {
            $query->whereDate('created_at', '<=', $asOfDate);
        }

        $debit = (string) ($query->clone()->sum('debit') ?? '0');
        $credit = (string) ($query->clone()->sum('credit') ?? '0');

        if ($type === LedgerAccountType::ACCOUNT) {
            return bcsub($debit, $credit, 2);
        }

        return bcsub($credit, $debit, 2);
    }

    public function sumDebits(): string
    {
        return (string) (LedgerEntry::sum('debit') ?? '0');
    }

    public function sumCredits(): string
    {
        return (string) (LedgerEntry::sum('credit') ?? '0');
    }

    public function sumByAccountType(LedgerAccountType $type, string $column): string
    {
        return (string) (LedgerEntry::where('ledger_account_type', $type->value)->sum($column) ?? '0');
    }
}
