<?php

namespace Tests\Unit\Grant;

use App\Domains\Grant\Validators\GrantApplicationValidator;
use App\Enums\GrantApplicationStatus;
use App\Enums\GrantBeneficiaryType;
use App\Enums\GrantPaymentMethod;
use App\Exceptions\DomainException;
use App\Models\GrantApplication;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrantApplicationValidatorTest extends TestCase
{
    use RefreshDatabase;

    private GrantApplicationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->validator = app(GrantApplicationValidator::class);
    }

    #[Test]
    public function it_requires_verifier_before_verification(): void
    {
        $grant = GrantApplication::factory()->create(['assigned_verifier_id' => null]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Verifikator wajib diisi');

        $this->validator->assertReadyForVerification($grant);
    }

    #[Test]
    public function it_rejects_approved_amount_above_verified(): void
    {
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::PENDING_APPROVAL,
            'recommended_amount' => '200000.00',
            'verified_amount' => '200000.00',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tidak boleh lebih besar');

        $this->validator->assertApprovedAmount($grant, '250000.00');
    }

    #[Test]
    public function it_requires_bank_details_for_transfer(): void
    {
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::VERIFICATION,
            'payment_method' => GrantPaymentMethod::TRANSFER,
            'recipient_identity_number' => '123',
            'recipient_address' => 'Alamat',
            'verified_amount' => '100000.00',
            'verifier_notes' => 'OK',
            'assigned_verifier_id' => User::factory(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Rekening');

        $this->validator->assertReadyForApproval($grant);
    }

    #[Test]
    public function it_rejects_user_without_grant_verify_as_assignee(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('grant.verify');

        $this->validator->assertAssignableVerifier($user);
    }

    #[Test]
    public function organization_requires_complete_pic_data_before_approval(): void
    {
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::VERIFICATION,
            'beneficiary_type' => GrantBeneficiaryType::ORGANIZATION,
            'recipient_address' => 'Alamat organisasi',
            'verified_amount' => '100000.00',
            'verifier_notes' => 'OK',
            'assigned_verifier_id' => User::factory(),
            'organization_pic_name' => 'Ustaz Ahmad',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('kontak, dan hubungan PIC');

        $this->validator->assertReadyForApproval($grant);
    }

    #[Test]
    public function organization_pic_bank_account_requires_relationship_and_reason(): void
    {
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::VERIFICATION,
            'beneficiary_type' => GrantBeneficiaryType::ORGANIZATION,
            'recipient_address' => 'Alamat organisasi',
            'organization_pic_name' => 'Ustaz Ahmad',
            'organization_pic_contact' => '0812',
            'organization_pic_relationship' => 'Ketua',
            'payment_method' => GrantPaymentMethod::TRANSFER,
            'bank_name' => 'Bank Amanah',
            'bank_account_number' => '12345',
            'bank_account_holder' => 'Ustaz Ahmad',
            'bank_account_owner_type' => 'pic',
            'verified_amount' => '100000.00',
            'verifier_notes' => 'OK',
            'assigned_verifier_id' => User::factory(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('alasan penggunaan rekening pribadi PIC');

        $this->validator->assertReadyForApproval($grant);
    }
}
