<?php

namespace App\Http\Resources;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_type' => $this->transaction_type?->value ?? $this->transaction_type,
            'transaction_id' => $this->transaction_id,
            'ledger_account_type' => $this->ledger_account_type?->value ?? $this->ledger_account_type,
            'ledger_account_id' => $this->ledger_account_id,
            'debit' => bcadd((string) $this->debit, '0', 2),
            'credit' => bcadd((string) $this->credit, '0', 2),
            'reference' => $this->reference,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
