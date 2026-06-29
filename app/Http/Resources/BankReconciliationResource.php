<?php

namespace App\Http\Resources;

use App\Models\BankReconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankReconciliation */
class BankReconciliationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'statement_balance' => bcadd((string) $this->statement_balance, '0', 2),
            'system_balance' => bcadd((string) $this->system_balance, '0', 2),
            'difference' => bcadd((string) $this->difference, '0', 2),
            'status' => $this->status,
            'notes' => $this->notes,
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'account' => AccountResource::make($this->whenLoaded('account')),
            'lines' => BankReconciliationLineResource::collection($this->whenLoaded('lines')),
            'reconciling_items' => $this->when(isset($this->reconciling_items), $this->reconciling_items),
            'reconciling_total' => $this->when(isset($this->reconciling_total), $this->reconciling_total),
            'adjusted_difference' => $this->when(isset($this->adjusted_difference), $this->adjusted_difference),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
