<?php

namespace App\Services;

use App\Enums\LedgerAccountType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use Illuminate\Support\Collection;

/**
 * API saldo domain — selalu dihitung dari ledger (source of truth).
 */
class TrustFundBalanceService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function fundBalance(int $fundId): string
    {
        return $this->ledger->balanceForFund($fundId);
    }

    public function accountBalance(int $accountId): string
    {
        return $this->ledger->balanceForAccount($accountId);
    }

    public function hasSufficientFund(int $fundId, string $amount): bool
    {
        return bccomp($this->fundBalance($fundId), $amount, 2) >= 0;
    }

    public function hasSufficientAccount(int $accountId, string $amount): bool
    {
        return bccomp($this->accountBalance($accountId), $amount, 2) >= 0;
    }

    public function assertFundSufficient(int $fundId, string $amount): void
    {
        $balance = $this->fundBalance($fundId);
        if (bccomp($balance, $amount, 2) < 0) {
            throw InsufficientBalanceException::fund(Fund::find($fundId)?->name ?? "#{$fundId}", $balance, $amount);
        }
    }

    public function assertAccountSufficient(int $accountId, string $amount): void
    {
        $balance = $this->accountBalance($accountId);
        if (bccomp($balance, $amount, 2) < 0) {
            throw InsufficientBalanceException::account(Account::find($accountId)?->name ?? "#{$accountId}", $balance, $amount);
        }
    }

    /** @return Collection<int, array{id:int, code:string, name:string, balance:string}> */
    public function allFundBalances(): Collection
    {
        return Fund::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Fund $f) => [
                'id' => $f->id,
                'code' => $f->code,
                'name' => $f->name,
                'balance' => $this->fundBalance($f->id),
            ]);
    }

    /** @return Collection<int, array{id:int, code:string, name:string, balance:string}> */
    public function allAccountBalances(): Collection
    {
        return Account::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'balance' => $this->accountBalance($a->id),
            ]);
    }

    /** Agregat saldo seluruh kas/bank dari ledger. */
    public function totalAccountBalances(): string
    {
        $debit = (string) (LedgerEntry::where('ledger_account_type', LedgerAccountType::ACCOUNT->value)->sum('debit') ?? '0');
        $credit = (string) (LedgerEntry::where('ledger_account_type', LedgerAccountType::ACCOUNT->value)->sum('credit') ?? '0');

        return bcsub($debit, $credit, 2);
    }

    /** Agregat saldo seluruh Dana Amanah dari ledger. */
    public function totalFundBalances(): string
    {
        $debit = (string) (LedgerEntry::where('ledger_account_type', LedgerAccountType::FUND->value)->sum('debit') ?? '0');
        $credit = (string) (LedgerEntry::where('ledger_account_type', LedgerAccountType::FUND->value)->sum('credit') ?? '0');

        return bcsub($credit, $debit, 2);
    }
}
