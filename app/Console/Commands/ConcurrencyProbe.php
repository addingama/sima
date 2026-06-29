<?php

namespace App\Console\Commands;

use App\Enums\LedgerMovement;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Console\Command;

/**
 * Pembuktian anti-race condition untuk invariant saldo non-negatif.
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
            $ledger->postAmanahMovement(
                TransactionType::OPENING,
                0,
                $account->id,
                [['fund_id' => $fund->id, 'amount' => '100000.00']],
                LedgerMovement::IN,
                'CC setup',
            );
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
                $ledger->postAmanahMovement(
                    TransactionType::EXPENSE,
                    0,
                    $account->id,
                    [['fund_id' => $fund->id, 'amount' => $amount]],
                    LedgerMovement::OUT,
                    'CC withdraw',
                );
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
