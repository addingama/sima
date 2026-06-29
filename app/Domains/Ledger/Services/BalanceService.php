<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\Repositories\LedgerEntryRepository;
use App\Enums\LedgerAccountType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Fund;
use Illuminate\Support\Collection;

/** API saldo — selalu dihitung dari ledger (source of truth). */
class BalanceService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly LedgerEntryRepository $entries,
    ) {}

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

    public function totalAccountBalances(): string
    {
        $debit = $this->entries->sumByAccountType(LedgerAccountType::ACCOUNT, 'debit');
        $credit = $this->entries->sumByAccountType(LedgerAccountType::ACCOUNT, 'credit');

        return bcsub($debit, $credit, 2);
    }

    public function totalFundBalances(): string
    {
        $debit = $this->entries->sumByAccountType(LedgerAccountType::FUND, 'debit');
        $credit = $this->entries->sumByAccountType(LedgerAccountType::FUND, 'credit');

        return bcsub($credit, $debit, 2);
    }
}
