<?php

namespace App\Http\Resources;

use App\Models\BankFee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankFee */
class BankFeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fee_number' => $this->fee_number,
            'fee_date' => $this->fee_date?->toDateString(),
            'account_id' => $this->account_id,
            'fund_id' => $this->fund_id,
            'fee_type' => $this->fee_type,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'operational_liability_id' => $this->when($this->operational_liability_id !== null, $this->operational_liability_id),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversal_reason' => $this->when($this->reversed_at !== null, $this->reversal_reason),
            'account' => AccountResource::make($this->whenLoaded('account')),
            'fund' => FundResource::make($this->whenLoaded('fund')),
            'operational_liability' => $this->whenLoaded('operationalLiability'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
