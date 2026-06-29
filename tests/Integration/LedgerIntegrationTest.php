<?php

namespace Tests\Integration;

use App\Domains\Expense\Services\ExpenseReversalService;
use App\Domains\Expense\Services\ExpenseService;
use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Receipt\Services\ReceiptReversalService;
use App\Domains\Receipt\Services\ReceiptService;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
        $this->actor = User::factory()->create();
    }

    #[Test]
    public function it_maintains_global_invariant_accounts_equal_funds(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '300000.00');

        $receipts = app(ReceiptService::class);
        $balances = app(BalanceService::class);

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '500000.00',
        ], [['fund_id' => $fund->id, 'amount' => '500000.00']], $this->actor);

        $receipts->approve($receipts->submit($receipt, $this->actor), $this->actor);

        $this->assertSame($balances->totalAccountBalances(), $balances->totalFundBalances());
        $this->assertSame('800000.00', $balances->totalAccountBalances());
    }

    #[Test]
    public function it_keeps_ledger_entries_immutable(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '100000.00');

        $entry = LedgerEntry::first();
        $this->expectException(\LogicException::class);
        $entry->update(['debit' => '999999.00']);
    }

    #[Test]
    public function it_posts_balanced_double_entry_for_receipt(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $receipts = app(ReceiptService::class);

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '100000.00',
        ], [['fund_id' => $fund->id, 'amount' => '100000.00']], $this->actor);

        $receipts->approve($receipts->submit($receipt, $this->actor), $this->actor);

        $entries = LedgerEntry::where('transaction_type', TransactionType::RECEIPT->value)
            ->where('transaction_id', $receipt->id)
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame('100000.00', bcadd((string) $entries->sum('debit'), '0', 2));
        $this->assertSame('100000.00', bcadd((string) $entries->sum('credit'), '0', 2));
    }

    #[Test]
    public function it_reverses_expense_and_restores_opening_balance(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '300000.00');

        $expenses = app(ExpenseService::class);
        $reversal = app(ExpenseReversalService::class);
        $balances = app(BalanceService::class);

        $expense = $expenses->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '120000.00',
        ], [['fund_id' => $fund->id, 'amount' => '120000.00']], $this->actor);

        $expense = $expenses->approve(
            $expenses->verify($expenses->submit($expense, $this->actor), $this->actor),
            $this->actor,
        );

        $reversal->reverse($expense, $this->actor, 'salah input');

        $this->assertSame('300000.00', $balances->fundBalance($fund->id));
        $this->assertSame(app(LedgerService::class)->totalDebits(), app(LedgerService::class)->totalCredits());
    }

    #[Test]
    public function it_reverses_receipt_and_zeros_fund_balance(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $receipts = app(ReceiptService::class);
        $reversal = app(ReceiptReversalService::class);
        $balances = app(BalanceService::class);

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '250000.00',
        ], [['fund_id' => $fund->id, 'amount' => '250000.00']], $this->actor);

        $receipt = $receipts->approve($receipts->submit($receipt, $this->actor), $this->actor);
        $reversal->reverse($receipt, $this->actor, 'duplikat');

        $this->assertSame('0.00', $balances->fundBalance($fund->id));
    }
}
