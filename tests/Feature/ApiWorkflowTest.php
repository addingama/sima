<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemFundSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Fund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemFundSeeder::class);

        $admin = $this->userWithRole('admin');
        $this->account = Account::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'is_active' => true, 'created_by' => $admin->id]);
        $this->fund = Fund::create(['code' => 'ZKT', 'name' => 'Zakat', 'type' => 'restricted', 'is_active' => true, 'created_by' => $admin->id]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/receipts')->assertStatus(401);
    }

    public function test_bendahara_can_create_receipt(): void
    {
        Sanctum::actingAs($this->userWithRole('bendahara'));

        $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'channel' => 'transfer',
            'amount' => '500000.00',
            'allocations' => [['fund_id' => $this->fund->id, 'amount' => '500000.00']],
        ])->assertStatus(201)->assertJsonPath('data.status', 'draft');
    }

    public function test_verifikator_cannot_create_receipt(): void
    {
        Sanctum::actingAs($this->userWithRole('verifikator'));

        $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'channel' => 'transfer',
            'amount' => '500000.00',
            'allocations' => [['fund_id' => $this->fund->id, 'amount' => '500000.00']],
        ])->assertStatus(403);
    }

    public function test_allocation_mismatch_returns_422(): void
    {
        Sanctum::actingAs($this->userWithRole('bendahara'));

        $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'channel' => 'transfer',
            'amount' => '500000.00',
            'allocations' => [['fund_id' => $this->fund->id, 'amount' => '400000.00']],
        ])->assertStatus(422)->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    public function test_receipt_approval_flow_over_http(): void
    {
        Sanctum::actingAs($bendahara = $this->userWithRole('bendahara'));
        $create = $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'channel' => 'transfer',
            'amount' => '500000.00',
            'allocations' => [['fund_id' => $this->fund->id, 'amount' => '500000.00']],
        ])->assertStatus(201);
        $id = $create->json('data.id');

        $this->postJson("/api/receipts/{$id}/submit")->assertStatus(200)->assertJsonPath('data.status', 'submitted');

        // Bendahara tidak boleh approve.
        $this->postJson("/api/receipts/{$id}/approve")->assertStatus(403);

        // Ketua approve.
        Sanctum::actingAs($this->userWithRole('ketua'));
        $this->postJson("/api/receipts/{$id}/approve")->assertStatus(200)->assertJsonPath('data.status', 'approved');

        // Saldo dana bertambah.
        Sanctum::actingAs($this->userWithRole('admin'));
        $this->getJson("/api/funds/{$this->fund->id}")->assertStatus(200)->assertJsonPath('data.balance', '500000.00');
    }
}
