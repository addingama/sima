<?php

namespace App\Http\Resources;

use App\Models\BankReconciliationLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankReconciliationLine */
class BankReconciliationLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_reconciliation_id' => $this->bank_reconciliation_id,
            'ledger_entry_id' => $this->ledger_entry_id,
            'description' => $this->description,
            'amount' => bcadd((string) $this->amount, '0', 2),
            'line_type' => $this->line_type,
            'ledger_entry' => $this->whenLoaded('ledgerEntry'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
