<?php

namespace Tests\Integration;

use App\Domains\Expense\Services\ExpenseService;
use App\Domains\Ledger\Services\BalanceService;
use App\Enums\DisbursementStatus;
use App\Exceptions\DomainException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
        $this->actor = $this->makeUser('bendahara');
    }

    #[Test]
    public function it_runs_full_expense_workflow_with_verification_and_approval(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '500000.00');

        $expenses = app(ExpenseService::class);
        $balances = app(BalanceService::class);

        $expense = $expenses->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '150000.00',
        ], [['fund_id' => $fund->id, 'amount' => '150000.00']], $this->actor);

        $expense = $expenses->submit($expense, $this->actor);
        $expense = $expenses->verify($expense, $this->makeUser('verifikator'));
        $expense = $expenses->approve($expense, $this->makeUser('ketua'));

        $this->assertSame(DisbursementStatus::APPROVED, $expense->status);
        $this->assertSame('350000.00', $balances->fundBalance($fund->id));
        $this->assertSame('350000.00', $balances->accountBalance($account->id));

        $actions = Approval::where('approvable_id', $expense->id)->pluck('action')->map(fn ($a) => $a->value)->all();
        $this->assertContains('submitted', $actions);
        $this->assertContains('verified', $actions);
        $this->assertContains('approved', $actions);
        $this->assertContains('posted', $actions);
    }

    #[Test]
    public function it_rejects_expense_submission_when_balance_insufficient(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $expenses = app(ExpenseService::class);

        $expense = $expenses->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '100000.00',
        ], [['fund_id' => $fund->id, 'amount' => '100000.00']], $this->actor);

        $this->expectException(InsufficientBalanceException::class);
        $expenses->submit($expense, $this->actor);
    }

    #[Test]
    public function it_rejects_expense_at_submitted_status(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '200000.00');

        $expenses = app(ExpenseService::class);
        $expense = $expenses->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '50000.00',
        ], [['fund_id' => $fund->id, 'amount' => '50000.00']], $this->actor);

        $expense = $expenses->submit($expense, $this->actor);
        $expense = $expenses->reject($expense, $this->makeUser('ketua'), 'Tidak sesuai anggaran');

        $this->assertSame(DisbursementStatus::REJECTED, $expense->status);
    }

    #[Test]
    public function it_cannot_edit_approved_expense(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '200000.00');

        $expenses = app(ExpenseService::class);
        $expense = $expenses->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '50000.00',
        ], [['fund_id' => $fund->id, 'amount' => '50000.00']], $this->actor);

        $expense = $expenses->approve(
            $expenses->verify($expenses->submit($expense, $this->actor), $this->makeUser('verifikator')),
            $this->makeUser('ketua'),
        );

        $this->expectException(DomainException::class);
        $expenses->update($expense, ['description' => 'Ubah'], null, $this->actor);
    }
}
