<?php

namespace App\Http\Requests\Transfer;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListFundTransferRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['transfer_date', 'transfer_number', 'amount', 'created_at']), [
            'status' => ['nullable', 'string'],
            'from_fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'to_fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }
}
