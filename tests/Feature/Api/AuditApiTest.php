<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function auditor_can_list_audit_logs(): void
    {
        $admin = $this->makeUser('admin');
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($admin);

        $this->actingAsRole('bendahara');
        $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '25000.00',
            'allocations' => [['fund_id' => $fund->id, 'amount' => '25000.00']],
        ])->assertCreated();

        $this->actingAsRole('auditor');
        $this->getJson('/api/audits')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);
    }

    #[Test]
    public function bendahara_cannot_access_audit_logs(): void
    {
        $this->actingAsRole('bendahara');
        $this->getJson('/api/audits')->assertForbidden();
    }

    #[Test]
    public function auditor_can_filter_audit_by_event(): void
    {
        $admin = $this->makeUser('admin');
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($admin);

        $this->actingAsRole('bendahara');
        $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '10000.00',
            'allocations' => [['fund_id' => $fund->id, 'amount' => '10000.00']],
        ])->assertCreated();

        $this->actingAsRole('auditor');
        $this->getJson('/api/audits?event=created')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
