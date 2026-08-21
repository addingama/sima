<?php

namespace Tests\Feature\Api;

use App\Enums\DisbursementStatus;
use App\Enums\ReceiptStatus;
use App\Models\Disbursement;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function dashboard_includes_cash_flow_monthly_series(): void
    {
        $admin = $this->actingAsRole('admin');
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($admin);

        Receipt::create([
            'receipt_number' => 'RCP/DEMO/001',
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '100000.00',
            'status' => ReceiptStatus::APPROVED,
            'created_by' => $admin->id,
        ]);

        Disbursement::create([
            'disbursement_number' => 'DSB/DEMO/001',
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '40000.00',
            'status' => DisbursementStatus::APPROVED,
            'created_by' => $admin->id,
        ]);

        $response = $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.penerimaan_bulan_ini', '100000.00')
            ->assertJsonPath('data.pengeluaran_bulan_ini', '40000.00');

        $series = $response->json('data.cash_flow_monthly');
        $this->assertIsArray($series);
        $this->assertCount(6, $series);
        $this->assertSame(now()->format('Y-m'), $series[5]['month']);
        $this->assertSame('100000.00', $series[5]['penerimaan']);
        $this->assertSame('40000.00', $series[5]['pengeluaran']);
    }

    #[Test]
    public function dashboard_requires_report_view(): void
    {
        $this->actingAsRole('donatur');

        $this->getJson('/api/dashboard')->assertForbidden();
    }
}
