<?php

namespace App\Domains\Ledger\DTOs;

use App\Enums\TransactionType;

readonly class ReverseJournalDto
{
    public function __construct(
        public TransactionType $originalType,
        public int $originalTransactionId,
        public int $reversalTransactionId,
        public ?string $reference = null,
    ) {}
}
