<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membangun ulang saldo termaterialisasi dari ledger_entries (source of truth).
 * Dipakai untuk: backfill awal, perbaikan drift, atau setelah maintenance data.
 */
class RebuildBalances extends Command
{
    protected $signature = 'sima:rebuild-balances';

    protected $description = 'Membangun ulang account_balances & fund_balances dari ledger_entries.';

    public function handle(): int
    {
        DB::transaction(function () {
            $now = now();

            $accountSums = LedgerEntry::query()
                ->selectRaw('account_id, COALESCE(SUM(amount),0) as balance')
                ->groupBy('account_id')->pluck('balance', 'account_id');

            $fundSums = LedgerEntry::query()
                ->selectRaw('fund_id, COALESCE(SUM(amount),0) as balance')
                ->groupBy('fund_id')->pluck('balance', 'fund_id');

            foreach (Account::query()->pluck('id') as $id) {
                DB::table('account_balances')->updateOrInsert(
                    ['account_id' => $id],
                    ['balance' => bcadd((string) ($accountSums[$id] ?? '0'), '0', 2), 'updated_at' => $now, 'created_at' => $now]
                );
            }

            foreach (Fund::query()->pluck('id') as $id) {
                DB::table('fund_balances')->updateOrInsert(
                    ['fund_id' => $id],
                    ['balance' => bcadd((string) ($fundSums[$id] ?? '0'), '0', 2), 'updated_at' => $now, 'created_at' => $now]
                );
            }
        });

        $this->info('Saldo termaterialisasi berhasil dibangun ulang.');

        return self::SUCCESS;
    }
}
