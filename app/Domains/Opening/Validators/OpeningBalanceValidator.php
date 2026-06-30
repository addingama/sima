<?php

namespace App\Domains\Opening\Validators;

use App\Domains\Ledger\Services\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;

class OpeningBalanceValidator
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    /** @param array<int, array<string, mixed>> $lines */
    public function assertLines(array $lines): void
    {
        if ($lines === []) {
            throw new DomainException('Posting saldo awal memerlukan minimal satu baris.');
        }

        foreach ($lines as $line) {
            $accountId = (int) $line['account_id'];
            $fundId = (int) $line['fund_id'];
            $amount = bcadd((string) $line['amount'], '0', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                throw new DomainException('Nominal saldo awal harus lebih besar dari nol.');
            }

            $account = Account::findOrFail($accountId);
            if (! $account->is_active) {
                throw new DomainException("Akun \"{$account->name}\" tidak aktif.");
            }

            $fund = Fund::findOrFail($fundId);
            if (! $fund->is_active) {
                throw new DomainException("Dana \"{$fund->name}\" tidak aktif.");
            }

            $this->assertFundAllowed($fund);
            $this->assertAccountReadyForOpening($account);
        }
    }

    public function assertFundAllowed(Fund $fund): void
    {
        if (in_array($fund->system_key, [Fund::KEY_SUSPENSE, Fund::KEY_OPENING_EQUITY], true)) {
            throw new DomainException(
                "Saldo awal tidak boleh dialokasikan ke dana sistem \"{$fund->name}\". Pilih Dana Amanah operasional atau program."
            );
        }
    }

    public function assertAccountReadyForOpening(Account $account): void
    {
        $balance = $this->ledger->balanceForAccount($account->id);

        if (bccomp($balance, '0', 2) !== 0) {
            throw new DomainException(
                "Akun \"{$account->name}\" sudah memiliki saldo ({$balance}). Saldo awal hanya untuk akun dengan saldo nol."
            );
        }

        if ($this->hasOpeningEntriesForAccount($account->id)) {
            throw new DomainException(
                "Akun \"{$account->name}\" sudah pernah diposting saldo awal. Koreksi hanya lewat reversal."
            );
        }
    }

    private function hasOpeningEntriesForAccount(int $accountId): bool
    {
        return LedgerEntry::query()
            ->where('transaction_type', TransactionType::OPENING)
            ->where('ledger_account_type', LedgerAccountType::ACCOUNT)
            ->where('ledger_account_id', $accountId)
            ->exists();
    }
}
