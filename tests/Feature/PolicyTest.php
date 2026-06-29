<?php

namespace Tests\Feature;

use App\Enums\ReceiptStatus;
use App\Models\Account;
use App\Models\Fund;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemFundSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Uji otorisasi record-level via Policy (bukan hanya permission route). */
class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemFundSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_ketua_cannot_approve_draft_receipt(): void
    {
        $admin = $this->userWithRole('admin');
        $account = Account::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'is_active' => true, 'created_by' => $admin->id]);
        $fund = Fund::create(['code' => 'ZKT', 'name' => 'Zakat', 'type' => 'restricted', 'is_active' => true, 'created_by' => $admin->id]);

        $receipt = Receipt::create([
            'receipt_number' => 'RCP/TEST/001',
            'receipt_date' => now(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '100000.00',
            'status' => ReceiptStatus::DRAFT,
            'created_by' => $admin->id,
        ]);
        $receipt->allocations()->create([
            'fund_id' => $fund->id,
            'amount' => '100000.00',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($this->userWithRole('ketua'));
        $this->postJson("/api/receipts/{$receipt->id}/approve")->assertStatus(403);
    }

    public function test_bendahara_cannot_reverse_approved_receipt_without_permission(): void
    {
        $admin = $this->userWithRole('admin');
        $receipt = Receipt::create([
            'receipt_number' => 'RCP/TEST/002',
            'receipt_date' => now(),
            'account_id' => Account::create(['code' => 'K2', 'name' => 'K2', 'type' => 'cash', 'is_active' => true, 'created_by' => $admin->id])->id,
            'channel' => 'cash',
            'amount' => '50000.00',
            'status' => ReceiptStatus::APPROVED,
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($this->userWithRole('bendahara'));
        $this->postJson("/api/receipts/{$receipt->id}/reverse", ['reason' => 'test'])
            ->assertStatus(403);
    }
}
