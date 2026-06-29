<?php

namespace App\Domains\Ledger\DTOs;

use App\Enums\LedgerAccountType;

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
