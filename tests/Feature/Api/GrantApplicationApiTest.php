<?php

namespace Tests\Feature\Api;

use App\Models\GrantApplication;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrantApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function asisten_can_create_draft_without_verifier(): void
    {
        $this->actingAsRole('asisten_bendahara');

        $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.assigned_verifier_id', null)
            ->assertJsonPath('data.payment_method', 'cash');
    }

    #[Test]
    public function cannot_send_to_verification_without_assigned_verifier(): void
    {
        $this->actingAsRole('asisten_bendahara');
        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/grant-applications/{$id}/send-to-verification")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    #[Test]
    public function assigned_verifier_can_submit_for_approval_after_completing_data(): void
    {
        $id = $this->createAssignedInVerification();

        $this->putJson("/api/grant-applications/{$id}", [
            'recipient_identity_number' => '3201010101010001',
            'recipient_address' => 'Jl. Melati No. 1',
            'verified_amount' => '200000.00',
            'verifier_notes' => 'Data lengkap, layak dibantu.',
        ])->assertOk();

        $this->postJson("/api/grant-applications/{$id}/submit-for-approval")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.verified_amount', '200000.00');
    }

    #[Test]
    public function other_verifier_cannot_view_or_update_assigned_card(): void
    {
        $id = $this->createAssignedInVerification();

        $this->actingAsRole('verifikator');

        $this->getJson("/api/grant-applications/{$id}")->assertForbidden();
        $this->putJson("/api/grant-applications/{$id}", [
            'verifier_notes' => 'Bukan kartu saya',
        ])->assertForbidden();
        $this->getJson('/api/grant-applications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function ketua_sees_all_and_cannot_increase_approved_amount(): void
    {
        $id = $this->createPendingApproval();

        $this->actingAsRole('ketua');
        $this->getJson('/api/grant-applications')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/grant-applications/{$id}/approve", [
            'approved_amount' => '999999.00',
        ])->assertStatus(422);

        $this->postJson("/api/grant-applications/{$id}/approve", [
            'approved_amount' => '150000.00',
            'notes' => 'Disetujui sebagian',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_amount', '150000.00');
    }

    #[Test]
    public function assign_rejects_user_without_grant_verify(): void
    {
        $staff = $this->actingAsRole('asisten_bendahara');
        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/grant-applications/{$id}/assign", [
            'assigned_verifier_id' => $staff->id,
        ])->assertStatus(422);
    }

    #[Test]
    public function bendahara_creates_linked_disbursement_from_approved_grant(): void
    {
        $id = $this->createApprovedGrant('250000.00');
        $admin = $this->makeUser('admin');
        $account = $this->makeAccount($admin);
        $fund = $this->makeFund($admin);
        $this->seedOpening($account, $fund, '500000.00');
        $ledgerBefore = LedgerEntry::query()->count();

        $this->actingAsRole('bendahara');
        $response = $this->postJson("/api/grant-applications/{$id}/disbursements", [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'sources' => [['fund_id' => $fund->id, 'amount' => '250000.00']],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.disbursement.payee', 'Siti Aminah')
            ->assertJsonPath('data.disbursement.amount', '250000.00')
            ->assertJsonPath('data.disbursement.status', 'draft');

        $this->assertNotNull($response->json('data.disbursement_id'));
        $this->assertSame($ledgerBefore, LedgerEntry::query()->count());

        $this->postJson("/api/grant-applications/{$id}/disbursements", [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'sources' => [['fund_id' => $fund->id, 'amount' => '250000.00']],
        ])->assertStatus(422);
    }

    #[Test]
    public function linked_disbursement_rejects_fund_sources_that_do_not_match_approved_amount(): void
    {
        $id = $this->createApprovedGrant('250000.00');
        $admin = $this->makeUser('admin');
        $account = $this->makeAccount($admin);
        $fund = $this->makeFund($admin);
        $this->seedOpening($account, $fund, '500000.00');

        $this->actingAsRole('bendahara');
        $this->postJson("/api/grant-applications/{$id}/disbursements", [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'sources' => [['fund_id' => $fund->id, 'amount' => '1.00']],
        ])->assertStatus(422);
    }

    #[Test]
    public function cannot_create_linked_disbursement_from_draft(): void
    {
        $this->actingAsRole('asisten_bendahara');
        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $admin = $this->makeUser('admin');
        $account = $this->makeAccount($admin);
        $fund = $this->makeFund($admin);

        $this->postJson("/api/grant-applications/{$id}/disbursements", [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'sources' => [['fund_id' => $fund->id, 'amount' => '250000.00']],
        ])->assertForbidden();
    }

    #[Test]
    public function complete_requires_handover_photo_and_approved_disbursement(): void
    {
        Storage::fake('local');

        $id = $this->createApprovedGrant('250000.00');
        $this->actingAsRole('bendahara');

        $this->postJson("/api/grant-applications/{$id}/complete", [
            'handed_over_on' => now()->toDateString(),
        ])->assertStatus(422);

        $admin = $this->makeUser('admin');
        $account = $this->makeAccount($admin);
        $fund = $this->makeFund($admin);
        $this->seedOpening($account, $fund, '500000.00');

        $this->actingAsRole('bendahara');
        $disbursementId = $this->postJson("/api/grant-applications/{$id}/disbursements", [
            'disbursement_date' => now()->toDateString(),
            'account_id' => $account->id,
            'sources' => [['fund_id' => $fund->id, 'amount' => '250000.00']],
        ])->assertCreated()->json('data.disbursement.id');

        $this->actingAsRole('bendahara');
        $this->postJson("/api/disbursements/{$disbursementId}/submit")->assertOk();
        $this->actingAsRole('verifikator');
        $this->postJson("/api/disbursements/{$disbursementId}/verify")->assertOk();
        $this->actingAsRole('bendahara');
        $this->postJson("/api/disbursements/{$disbursementId}/approve")->assertOk();

        $ledgerCount = LedgerEntry::query()->count();

        $this->postJson("/api/grant-applications/{$id}/complete", [
            'handed_over_on' => now()->toDateString(),
        ])->assertStatus(422);

        $this->post('/api/attachments', [
            'attachable_type' => 'grant_application',
            'attachable_id' => $id,
            'title' => GrantApplication::ATTACHMENT_HANDOVER,
            'file' => UploadedFile::fake()->image('serah-terima.jpg', 100, 100),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->postJson("/api/grant-applications/{$id}/complete", [
            'handed_over_on' => now()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame($ledgerCount, LedgerEntry::query()->count());
    }

    #[Test]
    public function donatur_cannot_access_grant_applications(): void
    {
        $this->actingAsRole('donatur');

        $this->getJson('/api/grant-applications')->assertForbidden();
        $this->postJson('/api/grant-applications', $this->draftPayload())->assertForbidden();
    }

    #[Test]
    public function ketua_can_return_to_verification(): void
    {
        $id = $this->createPendingApproval();

        $this->actingAsRole('ketua');
        $this->postJson("/api/grant-applications/{$id}/return", [
            'reason' => 'Alamat belum lengkap',
        ])->assertOk()
            ->assertJsonPath('data.status', 'verification')
            ->assertJsonPath('data.return_reason', 'Alamat belum lengkap');
    }

    /** @return array<string, mixed> */
    private function draftPayload(): array
    {
        return [
            'recipient_name' => 'Siti Aminah',
            'recommended_amount' => '250000.00',
            'reason' => 'Bantuan sembako',
            'recommender_name' => 'Pak RT',
        ];
    }

    private function createAssignedInVerification(): int
    {
        $this->actingAsRole('asisten_bendahara');
        $verifier = $this->makeUser('verifikator');

        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/grant-applications/{$id}/assign", [
            'assigned_verifier_id' => $verifier->id,
        ])->assertOk();

        $this->postJson("/api/grant-applications/{$id}/send-to-verification")
            ->assertOk()
            ->assertJsonPath('data.status', 'verification');

        Sanctum::actingAs($verifier);

        return $id;
    }

    private function createPendingApproval(): int
    {
        $id = $this->createAssignedInVerification();

        $this->putJson("/api/grant-applications/{$id}", [
            'recipient_identity_number' => '3201010101010001',
            'recipient_address' => 'Jl. Melati No. 1',
            'verified_amount' => '200000.00',
            'verifier_notes' => 'Layak',
        ])->assertOk();

        $this->postJson("/api/grant-applications/{$id}/submit-for-approval")
            ->assertOk();

        return $id;
    }

    private function createApprovedGrant(string $amount): int
    {
        $id = $this->createAssignedInVerification();

        $this->putJson("/api/grant-applications/{$id}", [
            'recipient_identity_number' => '3201010101010001',
            'recipient_address' => 'Jl. Melati No. 1',
            'verified_amount' => $amount,
            'verifier_notes' => 'Layak',
        ])->assertOk();

        $this->postJson("/api/grant-applications/{$id}/submit-for-approval")->assertOk();

        $this->actingAsRole('ketua');
        $this->postJson("/api/grant-applications/{$id}/approve", [
            'approved_amount' => $amount,
        ])->assertOk();

        return $id;
    }
}
