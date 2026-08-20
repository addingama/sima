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
            'statement_date' => $this->statement_date?->toDateString(),
            'statement_ref' => $this->statement_ref,
            'statement_amount' => $this->statement_amount !== null
                ? bcadd((string) $this->statement_amount, '0', 2)
                : null,
            'is_matched' => (bool) $this->is_matched,
            'note' => $this->note,
            'ledger_entry' => $this->whenLoaded('ledgerEntry'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
