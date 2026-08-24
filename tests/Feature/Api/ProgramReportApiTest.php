<?php

namespace Tests\Feature\Api;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SimaTestHelpers;
use Tests\TestCase;

class ProgramReportApiTest extends TestCase
{
    use RefreshDatabase;
    use SimaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function it_reports_program_budget_allocated_income_and_spent_amount(): void
    {
        $admin = $this->makeUser('admin');
        $account = $this->makeAccount($admin);
        $fund = $this->makeFund($admin);
        $program = Program::create([
            'code' => 'EVT-REPORT',
            'name' => 'Event Laporan',
            'event_type' => 'planned',
            'budget' => '1000000.00',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->seedOpening($account, $fund, '500000.00');

        $this->actingAsRole('bendahara');
        $receipt = $this->postJson('/api/receipts', [
            'receipt_date' => '2026-08-01',
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '800000.00',
            'allocations' => [[
                'fund_id' => $fund->id,
                'program_id' => $program->id,
                'amount' => '800000.00',
            ]],
        ])->assertCreated()->json('data');
        $this->postJson("/api/receipts/{$receipt['id']}/submit")->assertOk();
        $this->postJson("/api/receipts/{$receipt['id']}/approve")->assertOk();

        $expense = $this->postJson('/api/disbursements', [
            'disbursement_date' => '2026-08-02',
            'account_id' => $account->id,
            'program_id' => $program->id,
            'amount' => '300000.00',
            'sources' => [[
                'fund_id' => $fund->id,
                'program_id' => $program->id,
                'amount' => '300000.00',
            ]],
        ])->assertCreated()->json('data');
        $this->postJson("/api/disbursements/{$expense['id']}/submit")->assertOk();
        $this->postJson("/api/disbursements/{$expense['id']}/verify")->assertOk();
        $this->postJson("/api/disbursements/{$expense['id']}/approve")->assertOk();

        $this->getJson("/api/reports/by-program?program_id={$program->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.anggaran_kegiatan', '1000000.00')
            ->assertJsonPath('data.summary.total_dana_dialokasikan', '800000.00')
            ->assertJsonPath('data.summary.total_dana_dikeluarkan', '300000.00')
            ->assertJsonPath('data.summary.sisa_dari_alokasi', '500000.00')
            ->assertJsonPath('data.summary.sisa_dari_anggaran', '700000.00')
            ->assertJsonCount(2, 'data.rows');
    }
}
