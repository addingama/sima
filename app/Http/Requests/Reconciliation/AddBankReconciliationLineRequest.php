<?php

namespace App\Http\Requests\Reconciliation;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;

class AddBankReconciliationLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BankReconciliation $bankReconciliation */
        $bankReconciliation = $this->route('bankReconciliation');

        return $this->user()->can('update', $bankReconciliation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ledger_entry_id' => ['nullable', 'exists:ledger_entries,id'],
            'statement_date' => ['nullable', 'date'],
            'statement_ref' => ['nullable', 'string', 'max:255'],
            'statement_amount' => ['nullable', 'numeric'],
            'is_matched' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }
}
