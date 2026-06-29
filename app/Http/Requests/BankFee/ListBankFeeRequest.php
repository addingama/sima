<?php

namespace App\Http\Requests\BankFee;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListBankFeeRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['fee_date', 'fee_number', 'amount', 'created_at']), [
            'status' => ['nullable', 'string'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }
}
