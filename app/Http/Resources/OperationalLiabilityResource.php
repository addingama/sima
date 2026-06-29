<?php

namespace App\Http\Resources;

use App\Models\OperationalLiability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OperationalLiability */
class OperationalLiabilityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'liability_number' => $this->liability_number,
            'liability_date' => $this->liability_date?->toDateString(),
            'creditor' => $this->creditor,
            'description' => $this->description,
            'fund_id' => $this->fund_id,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'amount_settled' => bcadd((string) $this->amount_settled, '0', 2),
            'status' => $this->status,
            'fund' => FundResource::make($this->whenLoaded('fund')),
            'program' => ProgramResource::make($this->whenLoaded('program')),
            'settled_disbursement' => DisbursementResource::make($this->whenLoaded('settledDisbursement')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
