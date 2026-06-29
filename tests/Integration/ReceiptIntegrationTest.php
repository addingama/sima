<?php

namespace Tests\Integration;

use App\Domains\Receipt\Services\ReceiptReversalService;
use App\Domains\Receipt\Services\ReceiptService;
use App\Enums\ReceiptStatus;
use App\Exceptions\DomainException;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReceiptIntegrationTest extends TestCase
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
    public function it_runs_full_receipt_workflow_with_approval_and_audit(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $receipts = app(ReceiptService::class);

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '750000.00',
        ], [['fund_id' => $fund->id, 'amount' => '750000.00']], $this->actor);

        $this->assertSame(ReceiptStatus::DRAFT, $receipt->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $receipt->getMorphClass(),
            'auditable_id' => $receipt->id,
            'event' => 'created',
        ]);

        $receipt = $receipts->submit($receipt, $this->actor);
        $this->assertSame(ReceiptStatus::SUBMITTED, $receipt->status);

        $ketua = $this->makeUser('ketua');
        $receipt = $receipts->approve($receipt, $ketua, 'OK');
        $this->assertSame(ReceiptStatus::APPROVED, $receipt->status);

        $approvalActions = Approval::where('approvable_id', $receipt->id)
            ->where('approvable_type', $receipt->getMorphClass())
            ->pluck('action')
            ->map(fn ($a) => $a->value)
            ->all();

        $this->assertContains('submitted', $approvalActions);
        $this->assertContains('approved', $approvalActions);
        $this->assertContains('posted', $approvalActions);
    }

    #[Test]
    public function it_rejects_receipt_and_records_approval(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $receipts = app(ReceiptService::class);

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '100000.00',
        ], [['fund_id' => $fund->id, 'amount' => '100000.00']], $this->actor);

        $receipt = $receipts->submit($receipt, $this->actor);
        $receipt = $receipts->reject($receipt, $this->makeUser('ketua'), 'Dokumen tidak lengkap');

        $this->assertSame(ReceiptStatus::REJECTED, $receipt->status);
        $this->assertDatabaseHas('approvals', [
            'approvable_id' => $receipt->id,
            'action' => 'rejected',
        ]);
    }

    #[Test]
    public function it_reverses_approved_receipt_and_restores_zero_balance(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $receipts = app(ReceiptService::class);
        $reversal = app(ReceiptReversalService::class);

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '200000.00',
        ], [['fund_id' => $fund->id, 'amount' => '200000.00']], $this->actor);

        $receipt = $receipts->approve($receipts->submit($receipt, $this->actor), $this->makeUser('ketua'));
        $receipt = $reversal->reverse($receipt, $this->makeUser('admin'), 'Salah input');

        $this->assertSame(ReceiptStatus::REVERSED, $receipt->status);
        $this->assertSame('0.00', app(\App\Domains\Ledger\Services\BalanceService::class)->fundBalance($fund->id));
    }

    #[Test]
    public function it_cannot_reverse_non_approved_receipt(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $receipts = app(ReceiptService::class);

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '50000.00',
        ], [['fund_id' => $fund->id, 'amount' => '50000.00']], $this->actor);

        $this->expectException(DomainException::class);
        app(ReceiptReversalService::class)->reverse($receipt, $this->actor, 'test');
    }
}
