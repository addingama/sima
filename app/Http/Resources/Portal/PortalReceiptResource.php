<?php

namespace App\Http\Resources\Portal;

use App\Http\Resources\ReceiptAllocationResource;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Receipt */
class PortalReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'receipt_date' => $this->receipt_date,
            'channel' => $this->channel,
            'reference_number' => $this->reference_number,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'description' => $this->description,
            'status' => $this->status,
            'account' => $this->whenLoaded('account'),
            'allocations' => ReceiptAllocationResource::collection($this->whenLoaded('allocations')),
        ];
    }
}
