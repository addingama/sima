<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalApiTest extends TestCase
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
    }

    #[Test]
    public function linked_donor_can_view_own_summary_and_donations(): void
    {
        $donorUser = $this->makeUser('donatur');
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'code' => 'DON/2026/000001',
            'name' => 'Budi Donatur',
            'type' => 'individu',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $other = Donor::create([
            'code' => 'DON/2026/000002',
            'name' => 'Lainnya',
            'type' => 'individu',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $ownReceiptId = $this->createApprovedReceipt($donor->id, '150000.00');
        $this->createApprovedReceipt($other->id, '999000.00');

        Sanctum::actingAs($donorUser);

        $this->getJson('/api/portal/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $donor->id)
            ->assertJsonPath('data.name', 'Budi Donatur');

        $this->getJson('/api/portal/summary')
            ->assertOk()
            ->assertJsonPath('data.total_donasi', '150000.00')
            ->assertJsonPath('data.jumlah_transaksi', 1);

        $donations = $this->getJson('/api/portal/donations')->assertOk();
        $this->assertCount(1, $donations->json('data'));
        $this->assertSame($ownReceiptId, $donations->json('data.0.id'));
    }

    #[Test]
    public function unlinked_user_gets_not_found(): void
    {
        $donorUser = $this->makeUser('donatur');
        Sanctum::actingAs($donorUser);

        $this->getJson('/api/portal/profile')->assertNotFound();
        $this->getJson('/api/portal/summary')->assertNotFound();
        $this->getJson('/api/portal/donations')->assertNotFound();
    }

    #[Test]
    public function admin_can_link_donor_when_creating_user(): void
    {
        $this->actingAsRole('admin');

        $donor = Donor::create([
            'code' => 'DON/2026/000010',
            'name' => 'Link Target',
            'type' => 'individu',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $create = $this->postJson('/api/users', [
            'name' => 'Portal User',
            'email' => 'portal@sima.test',
            'password' => 'password123',
            'role' => 'donatur',
            'donor_id' => $donor->id,
        ])->assertCreated();

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'user_id' => $create->json('data.id'),
        ]);
        $this->assertSame($donor->id, $create->json('data.donor_id'));
    }

    private function createApprovedReceipt(int $donorId, string $amount): int
    {
        $this->actingAsRole('bendahara');

        $create = $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'donor_id' => $donorId,
            'channel' => 'transfer',
            'amount' => $amount,
            'allocations' => [['fund_id' => $this->fund->id, 'amount' => $amount]],
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/receipts/{$id}/submit")->assertOk();

        $this->actingAsRole('ketua');
        $this->postJson("/api/receipts/{$id}/approve")->assertOk();

        return $id;
    }
}
