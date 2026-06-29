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
            'description' => $this->description,
            'type' => $this->type,
            'is_system' => $this->is_system,
            'system_key' => $this->system_key,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'balance' => $this->when(
                array_key_exists('balance', $this->resource->getAttributes()),
                fn () => bcadd((string) $this->resource->getAttributes()['balance'], '0', 2)
            ),
        ];
    }
}
