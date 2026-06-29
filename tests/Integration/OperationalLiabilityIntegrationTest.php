<?php

namespace Tests\Integration;

use App\Domains\Expense\Services\ExpenseService;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Services\OperationalLiabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OperationalLiabilityIntegrationTest extends TestCase
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
    public function it_creates_liability_with_audit_trail(): void
    {
        ['operational' => $operational] = $this->makeFinancialFixtures($this->actor);
        $service = app(OperationalLiabilityService::class);

        $liability = $service->create([
            'liability_date' => now()->toDateString(),
            'creditor' => 'Vendor ABC',
            'description' => 'Tagihan listrik',
            'fund_id' => $operational->id,
            'amount' => '750000.00',
        ], $this->actor);

        $this->assertSame('outstanding', $liability->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $liability->getMorphClass(),
            'auditable_id' => $liability->id,
            'event' => 'created',
        ]);
    }

    #[Test]
    public function it_updates_only_outstanding_liability(): void
    {
        ['operational' => $operational] = $this->makeFinancialFixtures($this->actor);
        $service = app(OperationalLiabilityService::class);

        $liability = $service->create([
            'liability_date' => now()->toDateString(),
            'creditor' => 'Bank',
            'description' => 'Utang',
            'fund_id' => $operational->id,
            'amount' => '100000.00',
        ], $this->actor);

        $updated = $service->update($liability, ['description' => 'Utang diperbarui'], $this->actor);
        $this->assertSame('Utang diperbarui', $updated->description);
    }

    #[Test]
    public function it_settles_liability_with_approved_disbursement(): void
    {
        ['account' => $account, 'fund' => $fund, 'operational' => $operational] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '500000.00');

        $liabilityService = app(OperationalLiabilityService::class);
        $expenses = app(ExpenseService::class);

        $liability = $liabilityService->create([
            'liability_date' => now()->toDateString(),
            'creditor' => 'Vendor',
            'description' => 'Pembelian',
            'fund_id' => $operational->id,
            'amount' => '100000.00',
        ], $this->actor);

        $expense = $expenses->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '100000.00',
        ], [['fund_id' => $fund->id, 'amount' => '100000.00']], $this->actor);

        $expense = $expenses->approve(
            $expenses->verify($expenses->submit($expense, $this->actor), $this->makeUser('verifikator')),
            $this->makeUser('ketua'),
        );

        $settled = $liabilityService->settle($liability, $expense->id, $this->actor);

        $this->assertSame('settled', $settled->status);
        $this->assertSame('100000.00', (string) $settled->amount_settled);
    }

    #[Test]
    public function it_rejects_settle_with_non_approved_disbursement(): void
    {
        ['operational' => $operational, 'account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '200000.00');

        $liability = app(OperationalLiabilityService::class)->create([
            'liability_date' => now()->toDateString(),
            'creditor' => 'Vendor',
            'description' => 'Utang',
            'fund_id' => $operational->id,
            'amount' => '50000.00',
        ], $this->actor);

        $draftExpense = app(ExpenseService::class)->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '50000.00',
        ], [['fund_id' => $fund->id, 'amount' => '50000.00']], $this->actor);

        $this->expectException(DomainException::class);
        app(OperationalLiabilityService::class)->settle($liability, $draftExpense->id, $this->actor);
    }

    #[Test]
    public function it_voids_outstanding_liability(): void
    {
        ['operational' => $operational] = $this->makeFinancialFixtures($this->actor);

        $liability = app(OperationalLiabilityService::class)->create([
            'liability_date' => now()->toDateString(),
            'creditor' => 'Bank',
            'description' => 'Dibatalkan',
            'fund_id' => $operational->id,
            'amount' => '30000.00',
        ], $this->actor);

        $voided = app(OperationalLiabilityService::class)->void($liability, 'Tidak jadi', $this->actor);

        $this->assertSame('void', $voided->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $liability->id,
            'event' => 'voided',
        ]);
    }
}
