<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Fund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Fund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();

        $admin = $this->makeUser('admin');
        $this->account = $this->makeAccount($admin);
        $this->fund = $this->makeFund($admin);
    }

    #[Test]
    public function it_lists_receipts_with_envelope(): void
    {
        $this->actingAsRole('bendahara');

        $this->getJson('/api/receipts')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);
    }

    #[Test]
    public function it_reverses_approved_receipt_via_api(): void
    {
        $this->actingAsRole('bendahara');
        $create = $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'channel' => 'transfer',
            'amount' => '100000.00',
            'allocations' => [['fund_id' => $this->fund->id, 'amount' => '100000.00']],
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/receipts/{$id}/submit")->assertOk();
        $this->actingAsRole('ketua');
        $this->postJson("/api/receipts/{$id}/approve")->assertOk();

        $this->actingAsRole('admin');
        $this->postJson("/api/receipts/{$id}/reverse", ['reason' => 'Salah input'])
            ->assertOk()
            ->assertJsonPath('data.status', 'reversed');
    }

    #[Test]
    public function it_rejects_receipt_with_reason(): void
    {
        $this->actingAsRole('bendahara');
        $create = $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'channel' => 'cash',
            'amount' => '50000.00',
            'allocations' => [['fund_id' => $this->fund->id, 'amount' => '50000.00']],
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/receipts/{$id}/submit")->assertOk();

        $this->actingAsRole('ketua');
        $this->postJson("/api/receipts/{$id}/reject", ['reason' => 'Dokumen kurang'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }
}
