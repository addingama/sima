<?php

namespace App\Http\Resources;

use App\Models\OpeningBalanceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OpeningBalanceLine */
class OpeningBalanceLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_number' => $this->line_number,
            'account_id' => $this->account_id,
            'fund_id' => $this->fund_id,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'account' => AccountResource::make($this->whenLoaded('account')),
            'fund' => FundResource::make($this->whenLoaded('fund')),
        ];
    }
}
