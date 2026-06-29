<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Fund;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * API saldo untuk domain. Saldo dibaca dari cache termaterialisasi (O(1));
 * source of truth tetap ledger_entries (lihat LedgerService).
 *
 * Catatan: pengecekan di sini bersifat advisory (fail-fast / UX). Penjaga
 * sebenarnya yang anti-race adalah lockForUpdate di LedgerService::post().
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
            ->leftJoin('fund_balances', 'fund_balances.fund_id', '=', 'funds.id')
            ->orderBy('funds.name')
            ->get(['funds.id', 'funds.code', 'funds.name', DB::raw('COALESCE(fund_balances.balance, 0) as balance')])
            ->map(fn (Fund $f) => [
                'id' => $f->id,
                'code' => $f->code,
                'name' => $f->name,
                'balance' => bcadd((string) $f->balance, '0', 2),
            ]);
    }

    /** @return Collection<int, array{id:int, code:string, name:string, balance:string}> */
    public function allAccountBalances(): Collection
    {
        return Account::query()
            ->leftJoin('account_balances', 'account_balances.account_id', '=', 'accounts.id')
            ->orderBy('accounts.name')
            ->get(['accounts.id', 'accounts.code', 'accounts.name', DB::raw('COALESCE(account_balances.balance, 0) as balance')])
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'balance' => bcadd((string) $a->balance, '0', 2),
            ]);
    }
}
