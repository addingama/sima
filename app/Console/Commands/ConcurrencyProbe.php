<?php

namespace App\Console\Commands;

use App\Enums\LedgerType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Console\Command;

/**
 * Pembuktian anti-race condition untuk invariant saldo non-negatif.
 *
 *  php artisan sima:cc setup                 -> siapkan dana saldo 100.000
 *  php artisan sima:cc withdraw 80000        -> coba tarik 80.000 (jalankan 2x paralel)
 *  php artisan sima:cc status                -> tampilkan saldo akhir
 *
 * Dengan lockForUpdate, dua penarikan 80.000 paralel atas saldo 100.000 -> tepat
 * SATU berhasil, saldo akhir 20.000 (tidak pernah -60.000).
 */
class ConcurrencyProbe extends Command
{
    protected $signature = 'sima:cc {action} {amount=0}';

    protected $description = 'Uji konkurensi penjaga saldo (lockForUpdate).';

    public function handle(LedgerService $ledger): int
    {
        $actor = User::role('admin')->first();
        $action = $this->argument('action');

        $account = Account::firstOrCreate(['code' => 'CC-ACC'],
            ['name' => 'CC Akun', 'type' => 'cash', 'is_active' => true, 'created_by' => $actor?->id]);
        $fund = Fund::firstOrCreate(['code' => 'CC-FUND'],
            ['name' => 'CC Dana', 'type' => 'unrestricted', 'is_active' => true, 'created_by' => $actor?->id]);

        if ($action === 'setup') {
            $ledger->post([[
                'account_id' => $account->id, 'fund_id' => $fund->id,
                'amount' => '100000.00', 'type' => LedgerType::OPENING, 'memo' => 'CC setup',
            ]], $actor);
            $this->info('Setup: saldo dana = '.$ledger->balanceForFund($fund->id));

            return self::SUCCESS;
        }

        if ($action === 'status') {
            $this->info('Saldo dana = '.$ledger->balanceForFund($fund->id).' | akun = '.$ledger->balanceForAccount($account->id));

            return self::SUCCESS;
        }

        if ($action === 'withdraw') {
            $amount = bcadd((string) $this->argument('amount'), '0', 2);
            try {
                $ledger->post([[
                    'account_id' => $account->id, 'fund_id' => $fund->id,
                    'amount' => bcmul($amount, '-1', 2), 'type' => LedgerType::DISBURSEMENT, 'memo' => 'CC withdraw',
                ]], $actor);
                $this->info("WITHDRAW OK {$amount}");
            } catch (InsufficientBalanceException $e) {
                $this->warn('WITHDRAW DITOLAK: '.$e->getMessage());
            }

            return self::SUCCESS;
        }

        $this->error('action tidak dikenal');

        return self::FAILURE;
    }
}
