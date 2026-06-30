<?php

namespace Tests\Integration;

use App\Domains\Approval\Services\ApprovalService;
use App\Domains\Receipt\Services\ReceiptService;
use App\Enums\ApprovalAction;
use App\Exceptions\DomainException;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApprovalIntegrationTest extends TestCase
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
    public function it_records_approval_via_event_listener_on_receipt_submit(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $receipts = app(ReceiptService::class);

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '100000.00',
        ], [['fund_id' => $fund->id, 'amount' => '100000.00']], $this->actor);

        $receipts->submit($receipt, $this->actor);

        $this->assertDatabaseHas('approvals', [
            'approvable_type' => $receipt->getMorphClass(),
            'approvable_id' => $receipt->id,
            'action' => ApprovalAction::SUBMITTED->value,
            'actor_id' => $this->actor->id,
        ]);
    }

    #[Test]
    public function it_records_approval_and_posted_on_receipt_approve(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);
        $receipts = app(ReceiptService::class);
        $ketua = $this->makeUser('ketua');

        $receipt = $receipts->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '50000.00',
        ], [['fund_id' => $fund->id, 'amount' => '50000.00']], $this->actor);

        $receipt = $receipts->submit($receipt, $this->actor);
        $receipts->approve($receipt, $ketua, 'Disetujui');

        $count = Approval::where('approvable_id', $receipt->id)
            ->whereIn('action', [ApprovalAction::APPROVED, ApprovalAction::POSTED])
            ->count();

        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_rejects_recording_approval_for_unsupported_entity(): void
    {
        $this->expectException(DomainException::class);

        app(ApprovalService::class)->record(
            new User,
            ApprovalAction::APPROVED,
            $this->actor,
        );
    }
}
