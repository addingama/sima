<?php

namespace App\Http\Requests\PettyCash;

use App\Models\PettyCashFloat;
use Illuminate\Foundation\Http\FormRequest;

class ClosePettyCashFloatRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PettyCashFloat $float */
        $float = $this->route('pettyCashFloat');

        return $this->user()->can('close', $float);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'returned_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'receipt_date' => ['nullable', 'date'],
        ];
    }
}
