<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconciliationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();

        $this->admin = $this->makeUser('admin');
        $this->account = $this->makeAccount($this->admin);
        $fund = $this->makeFund($this->admin);
        $this->seedOpening($this->account, $fund, '1000000.00');
    }

    #[Test]
    public function it_creates_and_completes_reconciliation(): void
    {
        $this->actingAsRole('bendahara');

        $create = $this->postJson('/api/bank-reconciliations', [
            'account_id' => $this->account->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'statement_balance' => '1000000.00',
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->assertSame('draft', $create->json('data.status'));

        $this->postJson("/api/bank-reconciliations/{$id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    #[Test]
    public function it_shows_reconciliation_detail_with_reconciling_items(): void
    {
        $this->actingAsRole('bendahara');

        $feeId = $this->postJson('/api/bank-fees', [
            'fee_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'amount' => '20000.00',
            'fee_type' => 'admin',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/bank-fees/{$feeId}/post")->assertOk();

        $recon = $this->postJson('/api/bank-reconciliations', [
            'account_id' => $this->account->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'statement_balance' => '980000.00',
        ])->assertCreated();

        $this->getJson('/api/bank-reconciliations/'.$recon->json('data.id'))
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }
}
