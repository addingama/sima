<?php

namespace App\Http\Requests\Transfer;

use App\Models\FundTransfer;
use Illuminate\Foundation\Http\FormRequest;

class StoreFundTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FundTransfer::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'from_fund_id' => ['required', 'exists:funds,id', 'different:to_fund_id'],
            'to_fund_id' => ['required', 'exists:funds,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
