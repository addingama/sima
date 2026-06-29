<?php

namespace App\Models\Concerns;

use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasLedgerEntries
{
    abstract public function ledgerTransactionType(): TransactionType;

    /** Entri ledger yang berasal dari transaksi ini. */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id')
            ->where('transaction_type', $this->ledgerTransactionType()->value);
    }
}
