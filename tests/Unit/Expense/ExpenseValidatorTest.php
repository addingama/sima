<?php

namespace Tests\Unit\Expense;

use App\Domains\Expense\Validators\ExpenseValidator;
use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Ledger\Services\LedgerService;
use App\Enums\DisbursementStatus;
use App\Exceptions\DomainException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Disbursement;
use App\Models\ExpenseFundSource;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseValidatorTest extends TestCase
{
    use RefreshDatabase;

    private ExpenseValidator $validator;

    private User $actor;

    private Account $account;

    private Fund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(ExpenseValidator::class);
        $this->actor = User::factory()->create();
        $this->account = Account::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'is_active' => true, 'created_by' => $this->actor->id]);
        $this->fund = Fund::create(['code' => 'ZKT', 'name' => 'Zakat', 'type' => 'restricted', 'is_active' => true, 'created_by' => $this->actor->id]);
    }

    #[Test]
    public function it_requires_at_least_one_fund_source(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('minimal satu sumber Dana Amanah');

        $this->validator->assertSourcesMatch('100000.00', []);
    }

    #[Test]
    public function it_requires_source_total_to_match_expense_amount(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('harus sama dengan total pengeluaran');

        $this->validator->assertSourcesMatch('100000.00', [
            ['fund_id' => $this->fund->id, 'amount' => '50000.00'],
        ]);
    }

    #[Test]
    public function it_rejects_expense_when_fund_balance_insufficient(): void
    {
        $expense = Disbursement::create([
            'disbursement_number' => 'DSB-TEST',
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'amount' => '100000.00',
            'status' => DisbursementStatus::DRAFT->value,
            'created_by' => $this->actor->id,
        ]);

        ExpenseFundSource::create([
            'disbursement_id' => $expense->id,
            'fund_id' => $this->fund->id,
            'amount' => '100000.00',
            'created_by' => $this->actor->id,
        ]);

        $this->expectException(InsufficientBalanceException::class);
        $this->validator->assertFundsAvailable($expense);
    }

    #[Test]
    public function it_passes_when_fund_and_account_have_sufficient_balance(): void
    {
        $openingEquity = Fund::findBySystemKey(Fund::KEY_OPENING_EQUITY);
        app(LedgerService::class)->postOpeningBalanceLine(
            0,
            $this->account->id,
            $this->fund->id,
            $openingEquity->id,
            '300000.00',
            'Saldo awal',
        );

        $expense = Disbursement::create([
            'disbursement_number' => 'DSB-TEST',
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'amount' => '100000.00',
            'status' => DisbursementStatus::DRAFT->value,
            'created_by' => $this->actor->id,
        ]);

        ExpenseFundSource::create([
            'disbursement_id' => $expense->id,
            'fund_id' => $this->fund->id,
            'amount' => '100000.00',
            'created_by' => $this->actor->id,
        ]);

        $this->validator->assertFundsAvailable($expense);
        $this->assertSame('300000.00', app(BalanceService::class)->fundBalance($this->fund->id));
    }

    #[Test]
    public function it_allows_reversal_only_for_approved_expenses(): void
    {
        $draft = new Disbursement(['status' => DisbursementStatus::DRAFT]);
        $approved = new Disbursement(['status' => DisbursementStatus::APPROVED]);

        $this->expectException(DomainException::class);
        $this->validator->assertApprovedForReversal($draft);

        $this->validator->assertApprovedForReversal($approved);
        $this->assertTrue(true);
    }
}
