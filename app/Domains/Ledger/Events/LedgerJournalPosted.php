<?php

namespace App\Domains\Ledger\Events;

use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

class LedgerJournalPosted
{
    use Dispatchable;

    /** @param Collection<int, LedgerEntry> $entries */
    public function __construct(
        public readonly TransactionType $transactionType,
        public readonly int $transactionId,
        public readonly Collection $entries,
    ) {}
}
