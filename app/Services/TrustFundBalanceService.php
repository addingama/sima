<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use Illuminate\Support\Collection;

/**
 * Otoritas saldo. Saldo SELALU dihitung dari ledger_entries (source of truth).
 * Cache (bila ada) hanya optimisasi, bukan sumber kebenaran.
 */
class TrustFundBalanceService
{
    /** Saldo sebuah Dana Amanah. */
    public function fundBalance(int $fundId): string
    {
        return $this->normalize((string) (LedgerEntry::where('fund_id', $fundId)->sum('amount') ?? '0'));
    }

    /** Saldo sebuah Kas/Bank. */
    public function accountBalance(int $accountId): string
    {
        return $this->normalize((string) (LedgerEntry::where('account_id', $accountId)->sum('amount') ?? '0'));
    }

    public function hasSufficientFund(int $fundId, string $amount): bool
    {
        return bccomp($this->fundBalance($fundId), $amount, 2) >= 0;
    }

    public function hasSufficientAccount(int $accountId, string $amount): bool
    {
        return bccomp($this->accountBalance($accountId), $amount, 2) >= 0;
    }

    /** Lempar exception bila saldo Dana Amanah tidak cukup. */
    public function assertFundSufficient(int $fundId, string $amount): void
    {
        $balance = $this->fundBalance($fundId);
        if (bccomp($balance, $amount, 2) < 0) {
            $fund = Fund::find($fundId);
            throw InsufficientBalanceException::fund($fund?->name ?? "#{$fundId}", $balance, $amount);
        }
    }

    /** Lempar exception bila saldo Kas/Bank tidak cukup. */
    public function assertAccountSufficient(int $accountId, string $amount): void
    {
        $balance = $this->accountBalance($accountId);
        if (bccomp($balance, $amount, 2) < 0) {
            $account = Account::find($accountId);
            throw InsufficientBalanceException::account($account?->name ?? "#{$accountId}", $balance, $amount);
        }
    }

    /** @return Collection<int, array{id:int, code:string, name:string, balance:string}> */
    public function allFundBalances(): Collection
    {
        return Fund::query()->orderBy('name')->get()->map(fn (Fund $f) => [
            'id' => $f->id,
            'code' => $f->code,
            'name' => $f->name,
            'balance' => $this->fundBalance($f->id),
        ]);
    }

    /** @return Collection<int, array{id:int, code:string, name:string, balance:string}> */
    public function allAccountBalances(): Collection
    {
        return Account::query()->orderBy('name')->get()->map(fn (Account $a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'balance' => $this->accountBalance($a->id),
        ]);
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
