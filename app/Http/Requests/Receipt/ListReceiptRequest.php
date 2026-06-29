<?php

namespace App\Http\Requests\Receipt;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListReceiptRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['receipt_date', 'receipt_number', 'amount', 'created_at']), [
            'status' => ['nullable', 'string'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }
}
