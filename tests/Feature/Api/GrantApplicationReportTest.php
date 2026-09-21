<?php

namespace Tests\Feature\Api;

use App\Enums\GrantApplicationStatus;
use App\Enums\GrantPaymentMethod;
use App\Models\GrantApplication;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrantApplicationReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function auditor_sees_filtered_rows_and_org_summary(): void
    {
        $admin = $this->actingAsRole('admin');

        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::DRAFT,
            'recommended_amount' => '30000.00',
            'created_by' => $admin->id,
            'created_at' => '2026-01-10 08:00:00',
        ]);
        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::APPROVED,
            'recommended_amount' => '100000.00',
            'approved_amount' => '80000.00',
            'payment_method' => GrantPaymentMethod::CASH,
            'created_by' => $admin->id,
            'created_at' => '2026-02-01 09:00:00',
        ]);
        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::COMPLETED,
            'recommended_amount' => '50000.00',
            'approved_amount' => '50000.00',
            'created_by' => $admin->id,
            'created_at' => '2026-02-15 10:00:00',
        ]);
        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::REJECTED,
            'recommended_amount' => '20000.00',
            'created_by' => $admin->id,
            'created_at' => '2026-03-01 11:00:00',
        ]);

        $this->actingAsRole('auditor');

        $this->getJson('/api/reports/grant-applications')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.summary.jumlah_kartu', 4)
            ->assertJsonPath('meta.summary.antrian', 1)
            ->assertJsonPath('meta.summary.usulan', '200000.00')
            ->assertJsonPath('meta.summary.disetujui', '130000.00')
            ->assertJsonPath('meta.summary.siap_diserahkan', '80000.00')
            ->assertJsonPath('meta.summary.sudah_diserahkan', '50000.00')
            ->assertJsonPath('meta.summary.ditolak', '20000.00');

        $this->getJson('/api/reports/grant-applications?from=2026-02-01&to=2026-02-28')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.summary.jumlah_kartu', 2)
            ->assertJsonPath('meta.summary.antrian', 0)
            ->assertJsonPath('meta.summary.disetujui', '130000.00')
            ->assertJsonPath('meta.summary.ditolak', '0.00');

        $this->getJson('/api/reports/grant-applications?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.summary.jumlah_kartu', 1)
            ->assertJsonPath('meta.summary.siap_diserahkan', '80000.00')
            ->assertJsonPath('meta.summary.sudah_diserahkan', '0.00');

        $this->getJson('/api/reports/grant-applications?q=tidak-ada-penerima')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.summary.jumlah_kartu', 0);
    }

    #[Test]
    public function summary_counts_verification_and_pending_approval_as_queue(): void
    {
        $admin = $this->actingAsRole('admin');

        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::VERIFICATION,
            'recommended_amount' => '10000.00',
            'created_by' => $admin->id,
        ]);
        GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::PENDING_APPROVAL,
            'recommended_amount' => '20000.00',
            'created_by' => $admin->id,
        ]);

        $this->actingAsRole('auditor');

        $this->getJson('/api/reports/grant-applications')
            ->assertOk()
            ->assertJsonPath('meta.summary.jumlah_kartu', 2)
            ->assertJsonPath('meta.summary.antrian', 2)
            ->assertJsonPath('meta.summary.usulan', '30000.00')
            ->assertJsonPath('meta.summary.disetujui', '0.00');
    }

    #[Test]
    public function filters_by_payment_method_program_and_recipient_search(): void
    {
        $admin = $this->actingAsRole('admin');
        $program = Program::create([
            'code' => 'EVT-BNT-1',
            'name' => 'Khitan Masal',
            'event_type' => 'campaign',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $otherProgram = Program::create([
            'code' => 'EVT-BNT-2',
            'name' => 'Lainnya',
            'event_type' => 'campaign',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        GrantApplication::factory()->create([
            'recipient_name' => 'Siti Aminah',
            'recommended_amount' => '40000.00',
            'payment_method' => GrantPaymentMethod::CASH,
            'program_id' => $program->id,
            'created_by' => $admin->id,
        ]);
        GrantApplication::factory()->create([
            'recipient_name' => 'Budi Transfer',
            'recommended_amount' => '15000.00',
            'payment_method' => GrantPaymentMethod::TRANSFER,
            'program_id' => $otherProgram->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAsRole('auditor');

        $this->getJson('/api/reports/grant-applications?payment_method=cash')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_name', 'Siti Aminah')
            ->assertJsonPath('meta.summary.usulan', '40000.00');

        $this->getJson("/api/reports/grant-applications?program_id={$program->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.program.id', $program->id)
            ->assertJsonPath('meta.summary.jumlah_kartu', 1);

        $this->getJson('/api/reports/grant-applications?q=Siti')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_name', 'Siti Aminah');
    }

    #[Test]
    public function rejects_invalid_status_and_date_range(): void
    {
        $this->actingAsRole('auditor');

        $this->getJson('/api/reports/grant-applications?status=paid')
            ->assertUnprocessable();

        $this->getJson('/api/reports/grant-applications?from=2026-03-01&to=2026-02-01')
            ->assertUnprocessable();
    }

    #[Test]
    public function guest_cannot_view_grant_report(): void
    {
        $this->getJson('/api/reports/grant-applications')->assertUnauthorized();
    }

    #[Test]
    public function verifikator_only_sees_assigned_or_own_cards(): void
    {
        $verifier = $this->actingAsRole('verifikator');
        $asisten = $this->makeUser('asisten_bendahara');

        GrantApplication::factory()->create([
            'recipient_name' => 'Kartu assigned',
            'recommended_amount' => '10000.00',
            'assigned_verifier_id' => $verifier->id,
            'created_by' => $asisten->id,
        ]);
        GrantApplication::factory()->create([
            'recipient_name' => 'Kartu orang lain',
            'recommended_amount' => '99999.00',
            'created_by' => $asisten->id,
        ]);

        $this->getJson('/api/reports/grant-applications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_name', 'Kartu assigned')
            ->assertJsonPath('meta.summary.jumlah_kartu', 1)
            ->assertJsonPath('meta.summary.usulan', '10000.00');
    }

    #[Test]
    public function donatur_cannot_view_grant_report(): void
    {
        $this->actingAsRole('donatur');

        $this->getJson('/api/reports/grant-applications')->assertForbidden();
    }
}
