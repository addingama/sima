<?php

namespace App\Http\Resources;

use App\Models\ReceiptAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReceiptAllocation */
class ReceiptAllocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fund_id' => $this->fund_id,
            'program_id' => $this->program_id,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'note' => $this->note,
            'status' => $this->status?->value ?? $this->status,
            'fund' => FundResource::make($this->whenLoaded('fund')),
            'program' => ProgramResource::make($this->whenLoaded('program')),
        ];
    }
}
