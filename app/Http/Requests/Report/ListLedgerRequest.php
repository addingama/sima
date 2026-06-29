<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListLedgerRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['created_at', 'id', 'debit', 'credit']), [
            'ledger_account_type' => ['nullable', 'string'],
            'ledger_account_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'transaction_type' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }
}
