<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Drift-check: membandingkan saldo termaterialisasi (cache) dengan SUM(ledger_entries)
 * (source of truth). Idealnya dijalankan terjadwal (cron). Exit code != 0 bila ada drift,
 * sehingga bisa dipakai sebagai alarm di monitoring/DevOps.
 */
class CheckBalances extends Command
{
    protected $signature = 'sima:check-balances {--fix : Perbaiki otomatis bila ditemukan drift}';

    protected $description = 'Verifikasi integritas saldo cache vs ledger (source of truth).';

    public function handle(): int
    {
        $accountSums = LedgerEntry::query()
            ->selectRaw('account_id, COALESCE(SUM(amount),0) as balance')
            ->groupBy('account_id')->pluck('balance', 'account_id');
        $fundSums = LedgerEntry::query()
            ->selectRaw('fund_id, COALESCE(SUM(amount),0) as balance')
            ->groupBy('fund_id')->pluck('balance', 'fund_id');

        $cacheAccounts = DB::table('account_balances')->pluck('balance', 'account_id');
        $cacheFunds = DB::table('fund_balances')->pluck('balance', 'fund_id');

        $drift = [];

        foreach (Account::query()->pluck('name', 'id') as $id => $name) {
            $truth = bcadd((string) ($accountSums[$id] ?? '0'), '0', 2);
            $cache = bcadd((string) ($cacheAccounts[$id] ?? '0'), '0', 2);
            if (bccomp($truth, $cache, 2) !== 0) {
                $drift[] = ['type' => 'account', 'id' => $id, 'name' => $name, 'ledger' => $truth, 'cache' => $cache];
            }
        }

        foreach (Fund::query()->pluck('name', 'id') as $id => $name) {
            $truth = bcadd((string) ($fundSums[$id] ?? '0'), '0', 2);
            $cache = bcadd((string) ($cacheFunds[$id] ?? '0'), '0', 2);
            if (bccomp($truth, $cache, 2) !== 0) {
                $drift[] = ['type' => 'fund', 'id' => $id, 'name' => $name, 'ledger' => $truth, 'cache' => $cache];
            }
        }

        if (empty($drift)) {
            $this->info('OK: saldo cache konsisten dengan ledger.');

            return self::SUCCESS;
        }

        $this->error('DRIFT terdeteksi pada '.count($drift).' saldo:');
        $this->table(['Tipe', 'ID', 'Nama', 'Ledger', 'Cache'], array_map(fn ($d) => [
            $d['type'], $d['id'], $d['name'], $d['ledger'], $d['cache'],
        ], $drift));

        if ($this->option('fix')) {
            $this->call('sima:rebuild-balances');
            $this->info('Drift diperbaiki via rebuild.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
