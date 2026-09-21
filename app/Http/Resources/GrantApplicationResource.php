<?php

namespace App\Http\Resources;

use App\Models\GrantApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GrantApplication */
class GrantApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label(),
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'recipient_address' => $this->recipient_address,
            'recipient_identity_number' => $this->recipient_identity_number,
            'recommended_amount' => $this->recommended_amount !== null ? bcadd((string) $this->recommended_amount, '0', 2) : null,
            'verified_amount' => $this->verified_amount !== null ? bcadd((string) $this->verified_amount, '0', 2) : null,
            'approved_amount' => $this->approved_amount !== null ? bcadd((string) $this->approved_amount, '0', 2) : null,
            'reason' => $this->reason,
            'recommender_name' => $this->recommender_name,
            'recommender_contact' => $this->recommender_contact,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_holder' => $this->bank_account_holder,
            'notes' => $this->notes,
            'verifier_notes' => $this->verifier_notes,
            'decision_notes' => $this->decision_notes,
            'return_reason' => $this->return_reason,
            'rejection_reason' => $this->when($this->rejected_at !== null, $this->rejection_reason),
            'program_id' => $this->program_id,
            'assigned_verifier_id' => $this->assigned_verifier_id,
            'disbursement_id' => $this->disbursement_id,
            'sent_to_verification_at' => $this->sent_to_verification_at?->toIso8601String(),
            'submitted_for_approval_at' => $this->submitted_for_approval_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'handed_over_on' => $this->handed_over_on?->toDateString(),
            'handed_over_at' => $this->handed_over_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'program' => ProgramResource::make($this->whenLoaded('program')),
            'assigned_verifier' => $this->whenLoaded('assignedVerifier', fn () => $this->assignedVerifier === null ? null : [
                'id' => $this->assignedVerifier->id,
                'name' => $this->assignedVerifier->name,
            ]),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->createdBy === null ? null : [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'handed_over_by' => $this->whenLoaded('handedOverBy', fn () => $this->handedOverBy === null ? null : [
                'id' => $this->handedOverBy->id,
                'name' => $this->handedOverBy->name,
            ]),
            'disbursement' => DisbursementResource::make($this->whenLoaded('disbursement')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
