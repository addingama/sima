<?php

namespace App\Http\Requests\Liability;

use App\Models\OperationalLiability;
use Illuminate\Foundation\Http\FormRequest;

class StoreOperationalLiabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OperationalLiability::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'liability_date' => ['required', 'date'],
            'creditor' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fund_id' => ['nullable', 'exists:funds,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
