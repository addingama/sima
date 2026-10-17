<?php

namespace Tests\Feature\Api;

use App\Domains\Expense\Services\ExpenseService;
use App\Enums\PettyCashFloatPurpose;
use App\Models\PettyCashCategory;
use App\Models\PettyCashFloat;
use App\Models\PettyCashFunding;
use App\Models\PettyCashLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PettyCashReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function report_summarizes_funded_spent_and_outstanding(): void
    {
        $admin = $this->actingAsRole('admin');
        $pj = $this->makeUser('petugas_bantuan');
        $account = $this->makeAccount($admin);
        $fund = $this->makeFund($admin);
        $this->seedOpening($account, $fund, '500000.00');

        $category = PettyCashCategory::factory()->create(['name' => 'Internet']);
        $float = PettyCashFloat::factory()->reporting()->create([
            'custodian_user_id' => $pj->id,
            'created_by' => $admin->id,
            'purpose' => PettyCashFloatPurpose::OPERATIONAL,
            'title' => 'Ops Oktober',
        ]);

        $dsb = app(ExpenseService::class)->create([
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'amount' => '100000.00',
            'description' => 'Cair uji laporan',
        ], [['fund_id' => $fund->id, 'amount' => '100000.00']], $admin);

        $this->actingAsRole('asisten_bendahara');
        $this->postJson("/api/disbursements/{$dsb->id}/submit")->assertOk();
        $this->actingAsRole('verifikator');
        $this->postJson("/api/disbursements/{$dsb->id}/verify")->assertOk();
        $this->actingAsRole('bendahara');
        $this->postJson("/api/disbursements/{$dsb->id}/approve")->assertOk();

        PettyCashFunding::query()->create([
            'petty_cash_float_id' => $float->id,
            'disbursement_id' => $dsb->id,
            'kind' => 'initial',
            'amount' => '100000.00',
        ]);

        PettyCashLine::factory()->create([
            'petty_cash_float_id' => $float->id,
            'category_id' => $category->id,
            'amount' => '40000.00',
            'created_by' => $pj->id,
        ]);

        $this->actingAsRole('bendahara');
        $this->getJson('/api/reports/petty-cash')
            ->assertOk()
            ->assertJsonPath('meta.summary.float_count', 1)
            ->assertJsonPath('meta.summary.funded_total', '100000.00')
            ->assertJsonPath('meta.summary.spent_total', '40000.00')
            ->assertJsonPath('meta.summary.outstanding_total', '60000.00')
            ->assertJsonPath('data.0.float_number', $float->float_number)
            ->assertJsonPath('data.0.custodian_name', $pj->name)
            ->assertJsonPath('data.0.category_breakdown.0.category', 'Internet');
    }

    #[Test]
    public function donatur_cannot_view_petty_cash_report(): void
    {
        $this->actingAsRole('donatur');
        $this->getJson('/api/reports/petty-cash')->assertForbidden();
    }
}
