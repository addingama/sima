<?php

namespace Tests\Feature\Api;

use App\Enums\GrantApplicationStatus;
use App\Enums\GrantBeneficiaryType;
use App\Models\GrantApplication;
use App\Models\LedgerEntry;
use App\Models\User;
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
    public function list_filters_board_statuses_and_mine(): void
    {
        $admin = $this->actingAsRole('admin');
        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::DRAFT,
            'recipient_name' => 'Kartu Admin',
            'created_by' => $admin->id,
        ]);
        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::COMPLETED,
            'recipient_name' => 'Kartu Selesai',
            'created_by' => $admin->id,
        ]);
        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::DRAFT,
            'recipient_name' => 'Kartu Orang Lain',
            'created_by' => $this->makeUser('asisten_bendahara')->id,
        ]);

        $this->getJson('/api/grant-applications?status=draft,verification,pending_approval,approved')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/grant-applications?mine=1&status=draft,verification,pending_approval,approved')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/grant-applications?mine=true&status=draft,verification,pending_approval,approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_name', 'Kartu Admin');
    }

    #[Test]
    public function asisten_is_automatically_assigned_as_verifier_and_handover_officer(): void
    {
        $asisten = $this->actingAsRole('asisten_bendahara');

        $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.assigned_verifier_id', $asisten->id)
            ->assertJsonPath('data.assigned_handover_id', $asisten->id)
            ->assertJsonPath('data.payment_method', 'cash');
    }

    #[Test]
    public function creator_is_automatically_assigned_for_their_execution_permissions(): void
    {
        $user = $this->actingAsRole('petugas_bantuan');

        $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->assertJsonPath('data.created_by', $user->id)
            ->assertJsonPath('data.assigned_verifier_id', $user->id)
            ->assertJsonPath('data.assigned_handover_id', $user->id);
    }

    #[Test]
    public function organization_can_be_verified_without_an_identity_document(): void
    {
        $id = $this->createAssignedInVerification();

        $this->putJson("/api/grant-applications/{$id}", [
            'beneficiary_type' => GrantBeneficiaryType::ORGANIZATION->value,
            'recipient_name' => 'Masjid Al Amanah',
            'recipient_address' => 'Jl. Masjid No. 1',
            'organization_pic_name' => 'Ustaz Ahmad',
            'organization_pic_contact' => '08123456789',
            'organization_pic_relationship' => 'Ketua DKM',
            'verified_amount' => '250000.00',
            'verifier_notes' => 'Kebutuhan telah diperiksa.',
        ])->assertOk();

        $this->postJson("/api/grant-applications/{$id}/submit-for-approval")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.beneficiary_type', 'organization');
    }

    #[Test]
    public function cannot_send_to_verification_without_assigned_verifier(): void
    {
        $creator = User::factory()->create(['is_active' => true]);
        $creator->givePermissionTo(['grant.view', 'grant.create', 'grant.update', 'grant.handover']);
        Sanctum::actingAs($creator);
        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/grant-applications/{$id}/send-to-verification")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    #[Test]
    public function assigned_verifier_can_complete_missing_basic_data_and_attach_supporting_files(): void
    {
        $id = $this->createAssignedInVerification();

        $this->putJson("/api/grant-applications/{$id}", [
            'recipient_name' => 'Siti Aminah (diperbaiki)',
            'recipient_phone' => '08123456789',
            'recipient_identity_number' => '3201010101010001',
            'recipient_address' => 'Jl. Melati No. 1',
            'reason' => 'Bantuan sembako — dikonfirmasi di lapangan',
            'recommender_name' => 'Pak RT RW 05',
            'verified_amount' => '200000.00',
            'verifier_notes' => 'Data lengkap, layak dibantu.',
            'recommended_amount' => '999999.00',
        ])->assertOk()
            ->assertJsonPath('data.recipient_name', 'Siti Aminah (diperbaiki)')
            ->assertJsonPath('data.recipient_phone', '08123456789')
            ->assertJsonPath('data.reason', 'Bantuan sembako — dikonfirmasi di lapangan')
            ->assertJsonPath('data.recommended_amount', '250000.00');

        $this->post('/api/attachments', [
            'attachable_type' => 'grant_application',
            'attachable_id' => $id,
            'title' => 'kk',
            'file' => UploadedFile::fake()->image('kk.jpg', 80, 80),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->postJson("/api/grant-applications/{$id}/submit-for-approval")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.verified_amount', '200000.00');
    }

    #[Test]
    public function asisten_cannot_update_after_sent_to_verification(): void
    {
        $asisten = $this->actingAsRole('asisten_bendahara');
        $verifier = $this->makeUser('verifikator');

        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $this->actingAsRole('ketua');
        $this->postJson("/api/grant-applications/{$id}/assign", [
            'assigned_verifier_id' => $verifier->id,
        ])->assertOk();

        Sanctum::actingAs($asisten);
        $this->postJson("/api/grant-applications/{$id}/send-to-verification")->assertOk();

        Sanctum::actingAs($asisten);
        $this->putJson("/api/grant-applications/{$id}", [
            'recipient_address' => 'Tidak boleh diisi petugas input',
        ])->assertForbidden();
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
    public function roles_that_see_all_cannot_edit_a_card_unless_they_are_creator_or_assignee(): void
    {
        $creator = $this->actingAsRole('petugas_bantuan');
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::DRAFT,
            'created_by' => $creator->id,
        ]);

        foreach (['bendahara', 'ketua', 'auditor'] as $role) {
            $this->actingAsRole($role);
            $this->getJson("/api/grant-applications/{$grant->id}")->assertOk();
            $this->putJson("/api/grant-applications/{$grant->id}", [
                'reason' => "Perubahan oleh {$role}",
            ])->assertForbidden();
        }
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
    public function ketua_cannot_approve_without_an_assigned_handover_officer(): void
    {
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::PENDING_APPROVAL,
            'verified_amount' => '200000.00',
            'assigned_handover_id' => null,
        ]);

        $this->actingAsRole('ketua');
        $this->postJson("/api/grant-applications/{$grant->id}/approve", [
            'approved_amount' => '200000.00',
        ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    #[Test]
    public function only_assigned_handover_officer_or_admin_can_complete(): void
    {
        $assigned = $this->makeUser('bendahara');
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::APPROVED,
            'approved_amount' => '250000.00',
            'assigned_handover_id' => $assigned->id,
        ]);

        $this->actingAsRole('bendahara');
        $this->postJson("/api/grant-applications/{$grant->id}/complete", [
            'handed_over_on' => now()->toDateString(),
        ])->assertForbidden();
    }

    #[Test]
    public function reassigning_handover_after_approval_requires_a_reason(): void
    {
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::APPROVED,
            'approved_amount' => '250000.00',
            'assigned_handover_id' => $this->makeUser('bendahara')->id,
        ]);
        $replacement = $this->makeUser('bendahara');

        $this->actingAsRole('ketua');
        $this->postJson("/api/grant-applications/{$grant->id}/assign", [
            'assigned_handover_id' => $replacement->id,
        ])->assertStatus(422);

        $this->postJson("/api/grant-applications/{$grant->id}/assign", [
            'assigned_handover_id' => $replacement->id,
            'reason' => 'Petugas sebelumnya berhalangan.',
        ])->assertOk()
            ->assertJsonPath('data.assigned_handover_id', $replacement->id)
            ->assertJsonPath('data.handover_assignment_reason', 'Petugas sebelumnya berhalangan.');
    }

    #[Test]
    public function assign_rejects_user_without_grant_verify(): void
    {
        $this->actingAsRole('asisten_bendahara');
        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');
        $staff = User::factory()->create(['is_active' => true]);

        $this->actingAsRole('ketua');
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
        $handover = GrantApplication::query()->findOrFail($id)->assignedHandover;
        Sanctum::actingAs($handover);

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

        Sanctum::actingAs($handover);
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
    public function asisten_can_list_eligible_verifiers(): void
    {
        $verifier = $this->makeUser('verifikator');
        $this->actingAsRole('asisten_bendahara');

        $this->getJson('/api/grant-applications/verifiers')
            ->assertOk()
            ->assertJsonFragment(['id' => $verifier->id, 'name' => $verifier->name]);
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
            'beneficiary_type' => GrantBeneficiaryType::INDIVIDUAL->value,
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

        $handover = $this->makeUser('bendahara');
        $this->actingAsRole('ketua');
        $this->postJson("/api/grant-applications/{$id}/assign", [
            'assigned_verifier_id' => $verifier->id,
            'assigned_handover_id' => $handover->id,
        ])->assertOk();

        Sanctum::actingAs(GrantApplication::query()->findOrFail($id)->createdBy);
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
