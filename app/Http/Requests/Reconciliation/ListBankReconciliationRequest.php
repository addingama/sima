<?php

namespace App\Http\Requests\Reconciliation;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListBankReconciliationRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['period_end', 'period_start', 'created_at']), [
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'status' => ['nullable', 'string'],
        ]);
    }
}
