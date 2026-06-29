<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemFundSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemFundSeeder::class);
    }

    public function test_duplicate_idempotency_key_returns_same_receipt(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('bendahara');
        Sanctum::actingAs($user);

        $account = Account::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'is_active' => true, 'created_by' => $user->id]);
        $fund = Fund::create(['code' => 'ZKT', 'name' => 'Zakat', 'type' => 'restricted', 'is_active' => true, 'created_by' => $user->id]);

        $payload = [
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '100000.00',
            'allocations' => [['fund_id' => $fund->id, 'amount' => '100000.00']],
        ];

        $headers = ['Idempotency-Key' => 'test-key-receipt-001'];

        $first = $this->postJson('/api/receipts', $payload, $headers)->assertStatus(201);
        $second = $this->postJson('/api/receipts', $payload, $headers)->assertStatus(201);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, DB::table('receipts')->count());
    }
}
