<?php

namespace App\Domains\Ledger\DTOs;

use App\Enums\LedgerAccountType;
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

readonly class JournalLineDto
{
    public function __construct(
        public LedgerAccountType $ledgerAccountType,
        public int $ledgerAccountId,
        public string $debit,
        public string $credit,
    ) {}

    /** @return array{ledger_account_type: LedgerAccountType, ledger_account_id: int, debit: string, credit: string} */
    public function toArray(): array
    {
        return [
            'ledger_account_type' => $this->ledgerAccountType,
            'ledger_account_id' => $this->ledgerAccountId,
            'debit' => $this->debit,
            'credit' => $this->credit,
        ];
    }
}

readonly class ReverseJournalDto
{
    public function __construct(
        public TransactionType $originalType,
        public int $originalTransactionId,
        public int $reversalTransactionId,
        public ?string $reference = null,
    ) {}
}
