<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OperationalLiabilityApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Fund $operational;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();

        $this->admin = $this->makeUser('admin');
        $this->operational = Fund::findBySystemKey(Fund::KEY_OPERATIONAL);
    }

    #[Test]
    public function it_creates_and_lists_liabilities(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/liabilities', [
            'liability_date' => now()->toDateString(),
            'creditor' => 'PLN',
            'description' => 'Listrik',
            'fund_id' => $this->operational->id,
            'amount' => '500000.00',
        ])->assertCreated()->assertJsonPath('data.status', 'outstanding');

        $this->getJson('/api/liabilities')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);
    }

    #[Test]
    public function it_voids_liability_with_reason(): void
    {
        $this->actingAsRole('bendahara');
        $create = $this->postJson('/api/liabilities', [
            'liability_date' => now()->toDateString(),
            'creditor' => 'Vendor',
            'description' => 'Dibatalkan',
            'fund_id' => $this->operational->id,
            'amount' => '100000.00',
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/liabilities/{$id}/void", ['reason' => 'Tidak jadi'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void');
    }

    #[Test]
    public function it_settles_liability_with_approved_disbursement(): void
    {
        $account = $this->makeAccount($this->admin);
        $fund = $this->makeFund($this->admin);
        $this->seedOpening($account, $fund, '300000.00');

        $this->actingAsRole('bendahara');
        $liability = $this->postJson('/api/liabilities', [
            'liability_date' => now()->toDateString(),
            'creditor' => 'Vendor',
            'description' => 'Pembelian',
            'fund_id' => $this->operational->id,
            'amount' => '80000.00',
        ])->assertCreated();

        $liabilityId = $liability->json('data.id');

        $disbursement = $this->postJson('/api/disbursements', [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '80000.00',
            'sources' => [['fund_id' => $fund->id, 'amount' => '80000.00']],
        ])->assertCreated();

        $disbId = $disbursement->json('data.id');
        $this->postJson("/api/disbursements/{$disbId}/submit")->assertOk();
        $this->actingAsRole('verifikator');
        $this->postJson("/api/disbursements/{$disbId}/verify")->assertOk();
        $this->actingAsRole('ketua');
        $this->postJson("/api/disbursements/{$disbId}/approve")->assertOk();

        $this->actingAsRole('bendahara');
        $this->postJson("/api/liabilities/{$liabilityId}/settle", ['disbursement_id' => $disbId])
            ->assertOk()
            ->assertJsonPath('data.status', 'settled');
    }
}
