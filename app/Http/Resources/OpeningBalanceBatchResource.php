<?php

namespace App\Http\Resources;

use App\Models\OpeningBalanceBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OpeningBalanceBatch */
class OpeningBalanceBatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_number' => $this->batch_number,
            'opening_date' => $this->opening_date?->toDateString(),
            'reference' => $this->reference,
            'total_amount' => bcadd((string) $this->total_amount, '0', 2),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'posted_by' => $this->posted_by,
            'lines' => OpeningBalanceLineResource::collection($this->whenLoaded('lines')),
            'posted_by_user' => $this->whenLoaded('postedBy', fn () => [
                'id' => $this->postedBy->id,
                'name' => $this->postedBy->name,
                'email' => $this->postedBy->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
