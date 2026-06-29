<?php

namespace App\Http\Requests\Reconciliation;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;

class StoreBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BankReconciliation::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:accounts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'statement_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
