<?php

namespace App\Http\Resources;

use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Fund */
class FundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'is_system' => $this->when(isset($this->is_system), (bool) $this->is_system),
            'balance' => $this->when(
                array_key_exists('balance', $this->resource->getAttributes()),
                fn () => bcadd((string) $this->resource->getAttributes()['balance'], '0', 2)
            ),
        ];
    }
}
