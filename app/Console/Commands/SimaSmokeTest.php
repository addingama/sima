<?php

namespace App\Console\Commands;

use App\Enums\LedgerType;
use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use App\Services\BankFeeService;
use App\Services\ExpenseService;
use App\Services\LedgerService;
use App\Services\ReceiptService;
use App\Services\ReconciliationService;
use App\Services\ReversalService;
use App\Services\TrustFundBalanceService;
use Illuminate\Console\Command;
use OwenIt\Auditing\Models\Audit;

class SimaSmokeTest extends Command
{
    protected $signature = 'sima:smoke';

    protected $description = 'Smoke test service layer inti SIMA (ledger, receipt, expense, bank fee, reversal, reconciliation).';

    public function handle(
        LedgerService $ledger,
        TrustFundBalanceService $balances,
        ReceiptService $receipts,
        ExpenseService $expenses,
        BankFeeService $bankFees,
        ReversalService $reversal,
        ReconciliationService $reconcile,
    ): int {
        $actor = User::role('admin')->firstOrFail();
        $this->info("Aktor: {$actor->name}");

        // Master data uji
        $account = Account::firstOrCreate(
            ['code' => 'SMOKE-KAS'],
            ['name' => 'Kas Smoke Test', 'type' => 'cash', 'is_active' => true, 'created_by' => $actor->id]
        );
        $operasional = Fund::findBySystemKey(Fund::KEY_OPERATIONAL);
        $zakat = Fund::firstOrCreate(
            ['code' => 'SMOKE-ZAKAT'],
            ['name' => 'Dana Zakat', 'type' => 'restricted', 'is_active' => true, 'created_by' => $actor->id]
        );

        // Saldo awal: kas + 1.000.000 lawan Dana Operasional (untuk uji expense & bank fee)
        $ledger->post([[
            'account_id' => $account->id, 'fund_id' => $operasional->id,
            'amount' => '1000000.00', 'type' => LedgerType::OPENING,
            'memo' => 'Saldo awal smoke',
        ]], $actor);
        $this->line('Saldo awal kas: '.$balances->accountBalance($account->id));

        // 1) RECEIPT: 500.000 -> alokasi penuh ke Zakat
        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '500000.00',
            'description' => 'Donasi zakat smoke',
        ], [
            ['fund_id' => $zakat->id, 'amount' => '500000.00'],
        ], $actor);
        $receipt = $receipts->submit($receipt, $actor);
        $receipt = $receipts->approve($receipt, $actor);
        $this->line("Receipt {$receipt->receipt_number} status={$receipt->status->value}");
        $this->line('  Saldo Zakat: '.$balances->fundBalance($zakat->id).' | Kas: '.$balances->accountBalance($account->id));

        // 2) EXPENSE: 200.000 dari Zakat
        $expense = $expenses->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '200000.00',
            'payee' => 'Mustahik A',
        ], [
            ['fund_id' => $zakat->id, 'amount' => '200000.00'],
        ], $actor);
        $expense = $expenses->submit($expense, $actor);
        $expense = $expenses->verify($expense, $actor);
        $expense = $expenses->approve($expense, $actor);
        $this->line("Expense {$expense->disbursement_number} status={$expense->status->value}");
        $this->line('  Saldo Zakat: '.$balances->fundBalance($zakat->id).' | Kas: '.$balances->accountBalance($account->id));

        // 3) BANK FEE cukup: 6.500 dari Dana Operasional
        $fee = $bankFees->create([
            'fee_date' => now()->toDateString(),
            'account_id' => $account->id,
            'fee_type' => 'admin',
            'amount' => '6500.00',
        ], $actor);
        $fee = $bankFees->post($fee, $actor);
        $this->line("Bank fee {$fee->fee_number} status={$fee->status->value} (Operasional sisa: ".$balances->fundBalance($operasional->id).')');

        // 4) BANK FEE tidak cukup -> deferred (liability). Dana baru saldo 0.
        $emptyOps = Fund::firstOrCreate(
            ['code' => 'SMOKE-OPS2'],
            ['name' => 'Dana Ops Kosong', 'type' => 'unrestricted', 'is_active' => true, 'created_by' => $actor->id]
        );
        $fee2 = $bankFees->create([
            'fee_date' => now()->toDateString(),
            'account_id' => $account->id,
            'fund_id' => $emptyOps->id,
            'fee_type' => 'admin',
            'amount' => '15000.00',
        ], $actor);
        $fee2 = $bankFees->post($fee2, $actor);
        $this->line("Bank fee {$fee2->fee_number} status={$fee2->status->value} liability_id={$fee2->operational_liability_id}");

        // 5) REVERSAL expense -> saldo Zakat kembali
        $expense = $reversal->reverseExpense($expense, $actor, 'Salah penerima');
        $this->line("Reversal expense status={$expense->status->value} -> Saldo Zakat: ".$balances->fundBalance($zakat->id));

        // 6) RECONCILIATION (tidak mengubah ledger)
        $rec = $reconcile->create([
            'account_id' => $account->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'statement_balance' => $balances->accountBalance($account->id),
        ], $actor);
        $this->line("Rekonsiliasi: sistem={$rec->system_balance} statement={$rec->statement_balance} selisih={$rec->difference}");

        $this->newLine();
        $this->info('Audit log (action) tercatat: '.Audit::count().' baris');
        $this->info('SMOKE TEST OK');

        return self::SUCCESS;
    }
}
