<?php

namespace App\Http\Resources;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Receipt */
class ReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'receipt_date' => $this->receipt_date?->toDateString(),
            'account_id' => $this->account_id,
            'donor_id' => $this->donor_id,
            'channel' => $this->channel,
            'reference_number' => $this->reference_number,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->when($this->rejected_at !== null, $this->rejection_reason),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversal_reason' => $this->when($this->reversed_at !== null, $this->reversal_reason),
            'account' => AccountResource::make($this->whenLoaded('account')),
            'donor' => DonorResource::make($this->whenLoaded('donor')),
            'allocations' => ReceiptAllocationResource::collection($this->whenLoaded('allocations')),
            'approvals' => ApprovalResource::collection($this->whenLoaded('approvals')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
