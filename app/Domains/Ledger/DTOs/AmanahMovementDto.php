<?php

namespace App\Domains\Ledger\DTOs;

use App\Enums\LedgerMovement;
use App\Enums\TransactionType;

readonly class AmanahMovementDto
{
    /**
     * @param  array<int, array{fund_id: int, amount: string|float}>  $fundLines
     */
    public function __construct(
        public TransactionType $transactionType,
        public int $transactionId,
        public int $accountId,
        public array $fundLines,
        public LedgerMovement $movement,
        public ?string $reference = null,
    ) {}
}
