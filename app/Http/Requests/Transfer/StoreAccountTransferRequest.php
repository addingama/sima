<?php

namespace App\Http\Requests\Transfer;

use App\Models\AccountTransfer;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AccountTransfer::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'from_account_id' => ['required', 'exists:accounts,id', 'different:to_account_id'],
            'to_account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }
}
