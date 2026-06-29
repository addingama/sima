<?php

namespace Tests\Integration;

use App\Domains\Expense\Services\BankFeeService;
use App\Domains\Ledger\Services\BalanceService;
use App\Enums\BankFeeStatus;
use App\Exceptions\DomainException;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankFeeIntegrationTest extends TestCase
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
    public function it_posts_bank_fee_when_operational_fund_has_balance(): void
    {
        ['account' => $account, 'operational' => $operational] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $operational, '50000.00');

        $fees = app(BankFeeService::class);
        $balances = app(BalanceService::class);

        $fee = $fees->create([
            'fee_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '15000.00',
            'description' => 'Admin transfer',
        ], $this->actor);

        $fee = $fees->post($fee, $this->actor);

        $this->assertSame(BankFeeStatus::POSTED, $fee->status);
        $this->assertSame('35000.00', $balances->fundBalance($operational->id));
        $this->assertSame('35000.00', $balances->accountBalance($account->id));
    }

    #[Test]
    public function it_defers_bank_fee_and_creates_operational_liability_when_fund_insufficient(): void
    {
        ['account' => $account] = $this->makeFinancialFixtures($this->actor);
        $fees = app(BankFeeService::class);

        $fee = $fees->create([
            'fee_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '25000.00',
            'description' => 'Biaya admin',
        ], $this->actor);

        $fee = $fees->post($fee, $this->actor);

        $this->assertSame(BankFeeStatus::DEFERRED, $fee->status);
        $this->assertNotNull($fee->operational_liability_id);
        $this->assertDatabaseHas('operational_liabilities', [
            'id' => $fee->operational_liability_id,
            'status' => 'outstanding',
            'amount' => '25000.00',
        ]);
    }

    #[Test]
    public function it_rejects_bank_fee_on_restricted_fund(): void
    {
        ['account' => $account, 'fund' => $restricted] = $this->makeFinancialFixtures($this->actor);

        $this->expectException(DomainException::class);

        app(BankFeeService::class)->create([
            'fee_date' => now()->toDateString(),
            'account_id' => $account->id,
            'fund_id' => $restricted->id,
            'amount' => '10000.00',
        ], $this->actor);
    }

    #[Test]
    public function it_reverses_posted_bank_fee(): void
    {
        ['account' => $account, 'operational' => $operational] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $operational, '100000.00');

        $fees = app(BankFeeService::class);
        $balances = app(BalanceService::class);

        $fee = $fees->create([
            'fee_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '20000.00',
        ], $this->actor);

        $fee = $fees->post($fee, $this->actor);
        $fee = $fees->reverse($fee, $this->actor, 'Salah input');

        $this->assertSame(BankFeeStatus::REVERSED, $fee->status);
        $this->assertSame('100000.00', $balances->fundBalance($operational->id));
    }

    #[Test]
    public function it_defaults_to_operational_fund_when_not_specified(): void
    {
        ['account' => $account] = $this->makeFinancialFixtures($this->actor);
        $operational = Fund::findBySystemKey(Fund::KEY_OPERATIONAL);

        $fee = app(BankFeeService::class)->create([
            'fee_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '5000.00',
        ], $this->actor);

        $this->assertSame($operational->id, $fee->fund_id);
    }
}
