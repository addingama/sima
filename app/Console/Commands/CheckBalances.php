<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Fund;
use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Ledger\Services\LedgerService;
use Illuminate\Console\Command;

/**
 * Verifikasi invariant Amanah Ledger:
 * - total debit = total credit
 * - total kas/bank = total dana amanah
 * - tidak ada saldo negatif
 */
class CheckBalances extends Command
{
    protected $signature = 'sima:check-balances';

    protected $description = 'Verifikasi invariant ledger (double-entry & saldo non-negatif).';

    public function handle(LedgerService $ledger, BalanceService $balances): int
    {
        $issues = [];

        if (bccomp($ledger->totalDebits(), $ledger->totalCredits(), 2) !== 0) {
            $issues[] = 'Total debit ≠ total credit.';
        }

        if (bccomp($balances->totalAccountBalances(), $balances->totalFundBalances(), 2) !== 0) {
            $issues[] = 'Total kas/bank ≠ total Dana Amanah.';
        }

        foreach (Account::pluck('name', 'id') as $id => $name) {
            $balance = $ledger->balanceForAccount((int) $id);
            if (bccomp($balance, '0', 2) < 0) {
                $issues[] = "Saldo negatif akun {$name}: {$balance}";
            }
        }

        foreach (Fund::pluck('name', 'id') as $id => $name) {
            $balance = $ledger->balanceForFund((int) $id);
            if (bccomp($balance, '0', 2) < 0) {
                $issues[] = "Saldo negatif dana {$name}: {$balance}";
            }
        }

        if ($issues === []) {
            $this->info('OK: invariant ledger terpenuhi.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->error($issue);
        }

        return self::FAILURE;
    }
}
