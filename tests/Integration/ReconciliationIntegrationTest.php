<?php

namespace Tests\Integration;

use App\Domains\Expense\Services\BankFeeService;
use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Reconciliation\Services\ReconciliationService;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconciliationIntegrationTest extends TestCase
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
    public function it_creates_reconciliation_with_system_balance_and_difference(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '1000000.00');

        $recon = app(ReconciliationService::class)->create([
            'account_id' => $account->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'statement_balance' => '1000000.00',
            'notes' => 'Rekonsiliasi bulan ini',
        ], $this->actor);

        $this->assertSame('draft', $recon->status);
        $this->assertSame('1000000.00', (string) $recon->system_balance);
        $this->assertSame('0.00', (string) $recon->difference);
    }

    #[Test]
    public function it_completes_reconciliation_without_modifying_ledger(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '500000.00');

        $service = app(ReconciliationService::class);
        $ledgerCountBefore = LedgerEntry::count();

        $recon = $service->create([
            'account_id' => $account->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'statement_balance' => '500000.00',
        ], $this->actor);

        $completed = $service->complete($recon, $this->actor);

        $this->assertSame('completed', $completed->status);
        $this->assertNotNull($completed->reconciled_at);
        $this->assertSame($ledgerCountBefore, LedgerEntry::count());
    }

    #[Test]
    public function it_includes_deferred_bank_fees_in_reconciling_items(): void
    {
        ['account' => $account] = $this->makeFinancialFixtures($this->actor);
        $fees = app(BankFeeService::class);

        $fee = $fees->create([
            'fee_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '30000.00',
        ], $this->actor);

        $fees->post($fee, $this->actor);

        $service = app(ReconciliationService::class);
        $items = $service->deferredBankFeeItems($account->id, now()->toDateString());

        $this->assertCount(1, $items['items']);
        $this->assertSame('30000.00', $items['total']);
    }

    #[Test]
    public function it_recalculates_adjusted_difference_with_deferred_fees(): void
    {
        $service = app(ReconciliationService::class);

        $adjusted = $service->adjustedDifference('-30000.00', '30000.00');

        $this->assertSame('0.00', $adjusted);
    }

    #[Test]
    public function it_adds_line_only_on_draft_reconciliation(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $this->seedOpening($account, $fund, '100000.00');

        $service = app(ReconciliationService::class);
        $recon = $service->create([
            'account_id' => $account->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'statement_balance' => '100000.00',
        ], $this->actor);

        $entry = LedgerEntry::first();
        $line = $service->addLine($recon, [
            'ledger_entry_id' => $entry->id,
            'is_matched' => true,
            'note' => 'Cleared',
        ]);

        $this->assertSame($recon->id, $line->bank_reconciliation_id);
    }
}
