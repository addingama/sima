<?php

namespace App\Http\Resources;

use App\Models\OpeningBalanceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OpeningBalanceLine */
class OpeningBalanceReportLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->opening_balance_batch_id,
            'batch_number' => $this->batch->batch_number,
            'opening_date' => $this->batch->opening_date?->toDateString(),
            'reference' => $this->batch->reference,
            'line_number' => $this->line_number,
            'account_code' => $this->account->code,
            'account_name' => $this->account->name,
            'fund_code' => $this->fund->code,
            'fund_name' => $this->fund->name,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'posted_at' => $this->batch->posted_at?->toIso8601String(),
            'posted_by_name' => $this->batch->postedBy?->name,
        ];
    }
}
