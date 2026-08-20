<?php

namespace App\Http\Resources;

use App\Models\AccountTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccountTransfer */
class AccountTransferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'transfer_date' => $this->transfer_date?->toDateString(),
            'from_account_id' => $this->from_account_id,
            'to_account_id' => $this->to_account_id,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'reference_number' => $this->reference_number,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversal_reason' => $this->when($this->reversed_at !== null, $this->reversal_reason),
            'from_account' => AccountResource::make($this->whenLoaded('fromAccount')),
            'to_account' => AccountResource::make($this->whenLoaded('toAccount')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
