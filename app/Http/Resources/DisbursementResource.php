<?php

namespace App\Http\Resources;

use App\Models\Disbursement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Disbursement */
class DisbursementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disbursement_number' => $this->disbursement_number,
            'disbursement_date' => $this->disbursement_date?->toDateString(),
            'account_id' => $this->account_id,
            'program_id' => $this->program_id,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'payee' => $this->payee,
            'category' => $this->category,
            'reference_number' => $this->reference_number,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->when($this->rejected_at !== null, $this->rejection_reason),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversal_reason' => $this->when($this->reversed_at !== null, $this->reversal_reason),
            'account' => AccountResource::make($this->whenLoaded('account')),
            'program' => ProgramResource::make($this->whenLoaded('program')),
            'fund_sources' => ExpenseFundSourceResource::collection($this->whenLoaded('fundSources')),
            'approvals' => ApprovalResource::collection($this->whenLoaded('approvals')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
