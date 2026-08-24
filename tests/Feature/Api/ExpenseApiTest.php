<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $account;

    private Fund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();

        $this->admin = $this->makeUser('admin');
        $this->account = $this->makeAccount($this->admin);
        $this->fund = $this->makeFund($this->admin);
        $this->seedOpening($this->account, $this->fund, '500000.00');
    }

    #[Test]
    public function it_creates_and_approves_disbursement_via_api(): void
    {
        $this->actingAsRole('asisten_bendahara');
        $create = $this->postJson('/api/disbursements', [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'amount' => '75000.00',
            'sources' => [['fund_id' => $this->fund->id, 'amount' => '75000.00']],
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/disbursements/{$id}/submit")->assertOk();

        $this->actingAsRole('verifikator');
        $this->postJson("/api/disbursements/{$id}/verify")->assertOk();

        $this->actingAsRole('bendahara');
        $this->postJson("/api/disbursements/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    #[Test]
    public function it_returns_422_when_fund_sources_mismatch(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/disbursements', [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'amount' => '100000.00',
            'sources' => [['fund_id' => $this->fund->id, 'amount' => '50000.00']],
        ])->assertStatus(422)->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    #[Test]
    public function it_sets_payee_from_vendor_when_omitted(): void
    {
        $this->actingAsRole('bendahara');

        $vendor = Vendor::create([
            'code' => 'VND/2026/000001',
            'name' => 'PT Vendor Jaya',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->postJson('/api/disbursements', [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'vendor_id' => $vendor->id,
            'amount' => '25000.00',
            'sources' => [['fund_id' => $this->fund->id, 'amount' => '25000.00']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.vendor_id', $vendor->id)
            ->assertJsonPath('data.payee', 'PT Vendor Jaya');
    }

    #[Test]
    public function it_reverses_approved_disbursement(): void
    {
        $this->actingAsRole('bendahara');
        $create = $this->postJson('/api/disbursements', [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'amount' => '50000.00',
            'sources' => [['fund_id' => $this->fund->id, 'amount' => '50000.00']],
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/disbursements/{$id}/submit")->assertOk();
        $this->actingAsRole('verifikator');
        $this->postJson("/api/disbursements/{$id}/verify")->assertOk();
        $this->actingAsRole('ketua');
        $this->postJson("/api/disbursements/{$id}/approve")->assertOk();

        $this->actingAsRole('admin');
        $this->postJson("/api/disbursements/{$id}/reverse", ['reason' => 'Salah'])
            ->assertOk()
            ->assertJsonPath('data.status', 'reversed');
    }
}
