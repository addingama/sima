<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
        $this->seed(UserSeeder::class);
    }

    #[Test]
    public function seed_demo_creates_master_and_workflow_samples(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('accounts', ['code' => DemoDataSeeder::MARKER_ACCOUNT_CODE]);
        $this->assertDatabaseHas('funds', ['code' => 'DEMO-ZKT']);
        $this->assertDatabaseHas('donors', ['code' => 'DEMO-DON-001']);
        $this->assertDatabaseHas('vendors', ['code' => 'DEMO-VND-001']);
        $this->assertDatabaseHas('programs', ['code' => 'DEMO-PRG-001']);
        $this->assertDatabaseHas('opening_balance_batches', ['reference' => 'Saldo awal demo go-live']);
        $this->assertDatabaseHas('receipts', ['status' => 'submitted']);
        $this->assertDatabaseHas('receipts', ['status' => 'approved']);
        $this->assertDatabaseHas('disbursements', ['status' => 'verified']);
        $this->assertDatabaseHas('disbursements', ['status' => 'approved']);
        $this->assertDatabaseHas('bank_fees', ['status' => 'posted']);
        $this->assertDatabaseHas('account_transfers', ['status' => 'posted']);
        $this->assertDatabaseHas('operational_liabilities', ['status' => 'outstanding']);
        $this->assertDatabaseHas('bank_reconciliations', ['status' => 'draft']);

        $donatur = User::where('email', 'donatur@sima.test')->first();
        $this->assertNotNull($donatur);
        $this->assertTrue($donatur->hasRole('donatur'));
        $this->assertTrue(Donor::query()->where('user_id', $donatur->id)->exists());
    }

    #[Test]
    public function seed_demo_rejects_when_already_seeded(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(DemoDataSeeder::MARKER_ACCOUNT_CODE);

        $this->seed(DemoDataSeeder::class);
    }
}
