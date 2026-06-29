<?php

namespace Tests\Integration;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Audit\Services\AuditQueryService;
use App\Domains\Receipt\Services\ReceiptService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
        $this->actor = $this->makeUser('admin');
    }

    #[Test]
    public function it_logs_audit_on_manual_service_call(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);

        $receipt = app(ReceiptService::class)->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '50000.00',
        ], [['fund_id' => $fund->id, 'amount' => '50000.00']], $this->actor);

        $audit = app(AuditLogService::class)->log(
            $receipt,
            'manual_review',
            null,
            ['status' => 'reviewed'],
            $this->actor,
            'qa',
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $audit->id,
            'event' => 'manual_review',
            'user_id' => $this->actor->id,
        ]);
    }

    #[Test]
    public function it_queries_audit_logs_with_filters(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);

        app(ReceiptService::class)->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '10000.00',
        ], [['fund_id' => $fund->id, 'amount' => '10000.00']], $this->actor);

        $result = app(AuditQueryService::class)->paginate(
            new \App\Support\Query\ListQueryDto(filters: ['event' => 'created'], perPage: 10),
        );

        $this->assertGreaterThanOrEqual(1, $result->total());
    }

    #[Test]
    public function it_records_audit_via_receipt_created_event(): void
    {
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($this->actor);

        $receipt = app(ReceiptService::class)->create([
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '25000.00',
        ], [['fund_id' => $fund->id, 'amount' => '25000.00']], $this->actor);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $receipt->getMorphClass(),
            'auditable_id' => $receipt->id,
            'event' => 'created',
        ]);
    }
}
