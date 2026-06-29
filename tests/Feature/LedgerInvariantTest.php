<?php

namespace Tests\Feature;

use App\Domains\Expense\Services\ExpenseReversalService;
use App\Domains\Expense\Services\ExpenseService;
use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Receipt\Services\ReceiptService;
use App\Enums\DisbursementStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerInvariantTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Account $account;

    private Fund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
        $this->actor = User::factory()->create();
        $this->account = $this->makeAccount($this->actor);
        $this->fund = $this->makeFund($this->actor);
    }

    private function seedOpeningBalance(string $amount): void
    {
        $this->seedOpening($this->account, $this->fund, $amount);
    }

    public function test_receipt_full_flow_posts_ledger_and_balances(): void
    {
        $receipts = app(ReceiptService::class);
        $balances = app(BalanceService::class);

        $r = $receipts->create([
            'receipt_date' => now()->toDateString(), 'account_id' => $this->account->id,
            'channel' => 'transfer', 'amount' => '500000.00',
        ], [['fund_id' => $this->fund->id, 'amount' => '500000.00']], $this->actor);

        $r = $receipts->submit($r, $this->actor);
        $r = $receipts->approve($r, $this->actor);

        $this->assertSame(ReceiptStatus::APPROVED, $r->status);
        $this->assertSame('500000.00', $balances->fundBalance($this->fund->id));
        $this->assertSame('500000.00', $balances->accountBalance($this->account->id));
    }

    public function test_receipt_allocation_must_equal_total(): void
    {
        $this->expectException(DomainException::class);

        app(ReceiptService::class)->create([
            'receipt_date' => now()->toDateString(), 'account_id' => $this->account->id,
            'channel' => 'cash', 'amount' => '500000.00',
        ], [['fund_id' => $this->fund->id, 'amount' => '400000.00']], $this->actor);
    }

    public function test_expense_rejected_when_fund_insufficient(): void
    {
        $this->expectException(InsufficientBalanceException::class);

        $expenses = app(ExpenseService::class);
        $e = $expenses->create([
            'disbursement_date' => now()->toDateString(), 'account_id' => $this->account->id,
            'amount' => '100000.00',
        ], [['fund_id' => $this->fund->id, 'amount' => '100000.00']], $this->actor);

        $expenses->submit($e, $this->actor);
    }

    public function test_expense_flow_decrements_balances(): void
    {
        $this->seedOpeningBalance('300000.00');
        $expenses = app(ExpenseService::class);
        $balances = app(BalanceService::class);

        $e = $expenses->create([
            'disbursement_date' => now()->toDateString(), 'account_id' => $this->account->id,
            'amount' => '120000.00',
        ], [['fund_id' => $this->fund->id, 'amount' => '120000.00']], $this->actor);
        $e = $expenses->submit($e, $this->actor);
        $e = $expenses->verify($e, $this->actor);
        $e = $expenses->approve($e, $this->actor);

        $this->assertSame(DisbursementStatus::APPROVED, $e->status);
        $this->assertSame('180000.00', $balances->fundBalance($this->fund->id));
        $this->assertSame('180000.00', $balances->accountBalance($this->account->id));
    }

    public function test_reversal_restores_balance(): void
    {
        $this->seedOpeningBalance('300000.00');
        $expenses = app(ExpenseService::class);
        $reversal = app(ExpenseReversalService::class);
        $balances = app(BalanceService::class);

        $e = $expenses->create([
            'disbursement_date' => now()->toDateString(), 'account_id' => $this->account->id,
            'amount' => '120000.00',
        ], [['fund_id' => $this->fund->id, 'amount' => '120000.00']], $this->actor);
        $e = $expenses->approve($expenses->verify($expenses->submit($e, $this->actor), $this->actor), $this->actor);

        $e = $reversal->reverse($e, $this->actor, 'salah input');

        $this->assertSame(DisbursementStatus::REVERSED, $e->status);
        $this->assertSame('300000.00', $balances->fundBalance($this->fund->id));
    }

    public function test_global_invariant_accounts_equal_funds(): void
    {
        $this->seedOpeningBalance('300000.00');
        $receipts = app(ReceiptService::class);
        $balances = app(BalanceService::class);

        $r = $receipts->create([
            'receipt_date' => now()->toDateString(), 'account_id' => $this->account->id,
            'channel' => 'transfer', 'amount' => '500000.00',
        ], [['fund_id' => $this->fund->id, 'amount' => '500000.00']], $this->actor);
        $receipts->approve($receipts->submit($r, $this->actor), $this->actor);

        $this->assertSame(
            $balances->totalAccountBalances(),
            $balances->totalFundBalances()
        );
        $this->assertSame('800000.00', $balances->totalAccountBalances());
    }

    public function test_ledger_entry_is_immutable(): void
    {
        $this->seedOpeningBalance('100000.00');
        $entry = LedgerEntry::first();

        $this->expectException(\LogicException::class);
        $entry->update(['debit' => '999999.00']);
    }

    public function test_double_entry_balanced_per_transaction(): void
    {
        $this->seedOpeningBalance('100000.00');
        $ledger = app(LedgerService::class);

        $this->assertSame($ledger->totalDebits(), $ledger->totalCredits());
    }

    public function test_receipt_creates_ledger_entries(): void
    {
        $receipts = app(ReceiptService::class);
        $r = $receipts->create([
            'receipt_date' => now()->toDateString(), 'account_id' => $this->account->id,
            'channel' => 'transfer', 'amount' => '100000.00',
        ], [['fund_id' => $this->fund->id, 'amount' => '100000.00']], $this->actor);
        $receipts->approve($receipts->submit($r, $this->actor), $this->actor);

        $entries = LedgerEntry::where('transaction_type', TransactionType::RECEIPT->value)
            ->where('transaction_id', $r->id)
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame('100000.00', bcadd((string) $entries->sum('debit'), '0', 2));
        $this->assertSame('100000.00', bcadd((string) $entries->sum('credit'), '0', 2));
    }
}
