<?php

namespace App\Http\Requests\BankFee;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bankfee.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fee_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'fund_id' => ['nullable', 'exists:funds,id'],
            'fee_type' => ['required', 'in:admin,transfer,tax,other'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
        ];
    }
}
