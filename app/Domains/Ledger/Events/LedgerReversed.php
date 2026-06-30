<?php

namespace App\Domains\Ledger\Events;

use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

class LedgerReversed
{
    use Dispatchable;

    /** @param Collection<int, LedgerEntry> $entries */
    public function __construct(
        public readonly TransactionType $originalType,
        public readonly int $originalTransactionId,
        public readonly Collection $entries,
    ) {}
}
