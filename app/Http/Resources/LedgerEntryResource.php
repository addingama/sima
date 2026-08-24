<?php

namespace App\Http\Resources;

use App\Enums\LedgerAccountType;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = $this->ledger_account_type?->value ?? $this->ledger_account_type;
        $label = $this->ledger_account_label;
        $debit = bcadd((string) $this->debit, '0', 2);
        $credit = bcadd((string) $this->credit, '0', 2);
        $isDebit = bccomp($debit, '0', 2) > 0;

        return [
            'id' => $this->id,
            'transaction_type' => $this->transaction_type?->value ?? $this->transaction_type,
            'transaction_type_label' => $this->transactionTypeLabel(),
            'transaction_id' => $this->transaction_id,
            'ledger_account_type' => $type,
            'ledger_account_type_label' => $type === LedgerAccountType::ACCOUNT->value ? 'Kas/Bank' : 'Dana Amanah',
            'ledger_account_id' => $this->ledger_account_id,
            'ledger_account_code' => $label->code ?? null,
            'ledger_account_name' => $label->name ?? null,
            'ledger_account_label' => $this->formatAccountLabel($type, $label),
            'side' => $isDebit ? 'debit' : 'credit',
            'side_label' => $isDebit ? 'Debit' : 'Kredit',
            'debit' => $debit,
            'credit' => $credit,
            'reference' => $this->reference,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function transactionTypeLabel(): string
    {
        $type = $this->transaction_type instanceof TransactionType
            ? $this->transaction_type
            : TransactionType::tryFrom((string) $this->transaction_type);

        return match ($type) {
            TransactionType::OPENING => 'Saldo Awal',
            TransactionType::RECEIPT => 'Penerimaan',
            TransactionType::EXPENSE => 'Pengeluaran',
            TransactionType::BANK_FEE => 'Biaya Bank',
            TransactionType::REVERSAL => 'Reversal',
            TransactionType::ADJUSTMENT => 'Penyesuaian',
            TransactionType::OPERATIONAL_LIABILITY => 'Liabilitas Operasional',
            TransactionType::TRANSFER => 'Transfer',
            TransactionType::RECONCILIATION => 'Rekonsiliasi',
            default => (string) ($this->transaction_type?->value ?? $this->transaction_type),
        };
    }

    private function formatAccountLabel(mixed $type, mixed $label): ?string
    {
        if (! $label) {
            return null;
        }

        $prefix = $type === LedgerAccountType::ACCOUNT->value ? 'Kas/Bank' : 'Dana';
        $code = $label->code ?? null;
        $name = $label->name ?? null;

        if ($code && $name) {
            return "{$prefix}: {$code} — {$name}";
        }

        return $name ? "{$prefix}: {$name}" : ($code ? "{$prefix}: {$code}" : $prefix);
    }
}
