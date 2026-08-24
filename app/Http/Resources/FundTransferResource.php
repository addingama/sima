<?php

namespace App\Http\Resources;

use App\Models\FundTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FundTransfer */
class FundTransferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'transfer_date' => $this->transfer_date?->toDateString(),
            'from_fund_id' => $this->from_fund_id,
            'to_fund_id' => $this->to_fund_id,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'reference_number' => $this->reference_number,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversal_reason' => $this->when($this->reversed_at !== null, $this->reversal_reason),
            'from_fund' => FundResource::make($this->whenLoaded('fromFund')),
            'to_fund' => FundResource::make($this->whenLoaded('toFund')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
