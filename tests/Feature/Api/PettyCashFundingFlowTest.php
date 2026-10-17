<?php

namespace Tests\Feature\Api;

use App\Enums\PettyCashFloatPurpose;
use App\Enums\PettyCashFloatStatus;
use App\Enums\PettyCashTopupRequestStatus;
use App\Models\Account;
use App\Models\Fund;
use App\Models\PettyCashCategory;
use App\Models\PettyCashFloat;
use App\Models\PettyCashTopupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PettyCashFundingFlowTest extends TestCase
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
        $this->seedOpening($this->account, $this->fund, '1000000.00');
    }

    #[Test]
    public function approving_initial_disbursement_moves_float_to_reporting(): void
    {
        $pj = $this->makeUser('petugas_bantuan');
        $floatId = $this->createFloat($pj);

        $this->actingAsRole('bendahara');
        $this->postJson("/api/petty-cash-floats/{$floatId}/disbursements", [
            'amount' => '200000.00',
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'sources' => [['fund_id' => $this->fund->id, 'amount' => '200000.00']],
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $disbursementId = PettyCashFloat::query()->findOrFail($floatId)->fundings()->firstOrFail()->disbursement_id;
        $this->approveDisbursement($disbursementId);

        $this->getJson("/api/petty-cash-floats/{$floatId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'reporting')
            ->assertJsonPath('data.funded_total', '200000.00');
    }

    #[Test]
    public function pj_can_request_topup_and_bendahara_funds_it(): void
    {
        $pj = $this->makeUser('petugas_bantuan');
        $floatId = $this->createFundedFloat($pj, '100000.00');

        Sanctum::actingAs($pj);
        $topupId = $this->postJson("/api/petty-cash-floats/{$floatId}/topup-requests", [
            'amount' => '50000.00',
            'reason' => 'Tagihan internet naik',
        ])->assertCreated()->json('data.id');

        $this->actingAsRole('bendahara');
        $this->postJson("/api/petty-cash-topup-requests/{$topupId}/fund", [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'sources' => [['fund_id' => $this->fund->id, 'amount' => '50000.00']],
        ])->assertOk();

        $this->assertSame(
            PettyCashTopupRequestStatus::FUNDED,
            PettyCashTopupRequest::query()->findOrFail($topupId)->status
        );

        $topupDsb = PettyCashFloat::query()->findOrFail($floatId)->fundings()->where('kind', 'topup')->firstOrFail()->disbursement_id;
        $this->approveDisbursement($topupDsb);

        $this->getJson("/api/petty-cash-floats/{$floatId}")
            ->assertOk()
            ->assertJsonPath('data.funded_total', '150000.00');
    }

    #[Test]
    public function pj_closes_when_balanced_without_return(): void
    {
        $pj = $this->makeUser('petugas_bantuan');
        $floatId = $this->createFundedFloat($pj, '100000.00');
        $category = PettyCashCategory::factory()->create();

        Sanctum::actingAs($pj);
        $this->postJson("/api/petty-cash-floats/{$floatId}/lines", [
            'category_id' => $category->id,
            'spent_on' => now()->toDateString(),
            'amount' => '100000.00',
            'description' => 'Habis dipakai',
        ])->assertCreated();

        $this->postJson("/api/petty-cash-floats/{$floatId}/close", [
            'returned_amount' => '0.00',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.spent_total', '100000.00');
    }

    #[Test]
    public function close_with_remainder_creates_draft_receipt(): void
    {
        $pj = $this->makeUser('petugas_bantuan');
        $floatId = $this->createFundedFloat($pj, '100000.00');
        $category = PettyCashCategory::factory()->create();

        Sanctum::actingAs($pj);
        $this->postJson("/api/petty-cash-floats/{$floatId}/lines", [
            'category_id' => $category->id,
            'spent_on' => now()->toDateString(),
            'amount' => '70000.00',
            'description' => 'Sebagian',
        ])->assertCreated();

        $this->postJson("/api/petty-cash-floats/{$floatId}/close", [
            'returned_amount' => '30000.00',
            'account_id' => $this->account->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.returned_amount', '30000.00');

        $float = PettyCashFloat::query()->findOrFail($floatId);
        $this->assertNotNull($float->return_receipt_id);
        $this->assertDatabaseHas('receipts', [
            'id' => $float->return_receipt_id,
            'amount' => '30000.00',
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function close_rejects_unbalanced_totals(): void
    {
        $pj = $this->makeUser('petugas_bantuan');
        $floatId = $this->createFundedFloat($pj, '100000.00');
        $category = PettyCashCategory::factory()->create();

        Sanctum::actingAs($pj);
        $this->postJson("/api/petty-cash-floats/{$floatId}/lines", [
            'category_id' => $category->id,
            'spent_on' => now()->toDateString(),
            'amount' => '40000.00',
            'description' => 'Kurang',
        ])->assertCreated();

        $this->postJson("/api/petty-cash-floats/{$floatId}/close", [
            'returned_amount' => '0.00',
        ])->assertUnprocessable();
    }

    private function createFloat(User $pj): int
    {
        $this->actingAsRole('bendahara');

        return (int) $this->postJson('/api/petty-cash-floats', [
            'title' => 'Operasional Uji',
            'purpose' => PettyCashFloatPurpose::OPERATIONAL->value,
            'custodian_user_id' => $pj->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertCreated()->json('data.id');
    }

    private function createFundedFloat(User $pj, string $amount): int
    {
        $floatId = $this->createFloat($pj);
        $this->actingAsRole('bendahara');
        $this->postJson("/api/petty-cash-floats/{$floatId}/disbursements", [
            'amount' => $amount,
            'disbursement_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'sources' => [['fund_id' => $this->fund->id, 'amount' => $amount]],
        ])->assertCreated();

        $disbursementId = PettyCashFloat::query()->findOrFail($floatId)->fundings()->firstOrFail()->disbursement_id;
        $this->approveDisbursement($disbursementId);

        $this->assertSame(PettyCashFloatStatus::REPORTING, PettyCashFloat::query()->findOrFail($floatId)->status);

        return $floatId;
    }

    private function approveDisbursement(int $id): void
    {
        $this->actingAsRole('asisten_bendahara');
        $this->postJson("/api/disbursements/{$id}/submit")->assertOk();
        $this->actingAsRole('verifikator');
        $this->postJson("/api/disbursements/{$id}/verify")->assertOk();
        $this->actingAsRole('bendahara');
        $this->postJson("/api/disbursements/{$id}/approve")->assertOk();
    }
}
