<?php

namespace App\Http\Resources;

use App\Models\PettyCashTopupRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PettyCashTopupRequest */
class PettyCashTopupRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'petty_cash_float_id' => $this->petty_cash_float_id,
            'amount' => (string) $this->amount,
            'reason' => $this->reason,
            'status' => $this->status?->value,
            'funding_id' => $this->funding_id,
            'requested_by' => $this->requested_by,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at,
            'resolution_notes' => $this->resolution_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
