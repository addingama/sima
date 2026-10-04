<?php

namespace App\Models;

use App\Enums\GrantApplicationStatus;
use App\Enums\GrantBeneficiaryCountMethod;
use App\Enums\GrantBeneficiaryScope;
use App\Enums\GrantBeneficiaryType;
use App\Enums\GrantPaymentMethod;
use App\Models\Concerns\HasAttachments;
use Database\Factories\GrantApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class GrantApplication extends Model implements Auditable
{
    /** @use HasFactory<GrantApplicationFactory> */
    use AuditableTrait, HasAttachments, HasFactory;

    public const ATTACHMENT_IDENTITY = 'identity';

    public const ATTACHMENT_HANDOVER = 'handover';

    protected $fillable = [
        'application_number',
        'status',
        'beneficiary_type',
        'beneficiary_scope',
        'target_beneficiary_count',
        'actual_beneficiary_count',
        'beneficiary_count_method',
        'beneficiary_location',
        'beneficiary_count_notes',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_identity_number',
        'organization_pic_name',
        'organization_pic_contact',
        'organization_pic_relationship',
        'recommended_amount',
        'verified_amount',
        'approved_amount',
        'reason',
        'recommender_name',
        'recommender_contact',
        'payment_method',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'bank_account_owner_type',
        'bank_account_holder_relationship',
        'bank_account_use_reason',
        'notes',
        'verifier_notes',
        'decision_notes',
        'return_reason',
        'rejection_reason',
        'program_id',
        'assigned_verifier_id',
        'assigned_handover_id',
        'handover_assignment_reason',
        'disbursement_id',
        'sent_to_verification_at',
        'sent_to_verification_by',
        'submitted_for_approval_at',
        'submitted_for_approval_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'returned_at',
        'returned_by',
        'handed_over_on',
        'handed_over_at',
        'handed_over_by',
        'handover_recipient_name',
        'handover_recipient_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => GrantApplicationStatus::class,
            'beneficiary_type' => GrantBeneficiaryType::class,
            'beneficiary_scope' => GrantBeneficiaryScope::class,
            'beneficiary_count_method' => GrantBeneficiaryCountMethod::class,
            'target_beneficiary_count' => 'integer',
            'actual_beneficiary_count' => 'integer',
            'payment_method' => GrantPaymentMethod::class,
            'recommended_amount' => 'decimal:2',
            'verified_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'sent_to_verification_at' => 'datetime',
            'submitted_for_approval_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'returned_at' => 'datetime',
            'handed_over_on' => 'date',
            'handed_over_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function assignedVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_verifier_id');
    }

    public function assignedHandover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_handover_id');
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function hasIdentityDocument(): bool
    {
        return $this->attachments()
            ->where('title', self::ATTACHMENT_IDENTITY)
            ->exists();
    }

    public function hasHandoverPhoto(): bool
    {
        return $this->attachments()
            ->where('title', self::ATTACHMENT_HANDOVER)
            ->exists();
    }
}
