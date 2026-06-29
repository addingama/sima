<?php

namespace App\Http\Requests\Disbursement;

use App\Http\Requests\Concerns\ExpenseSourceRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreDisbursementRequest extends FormRequest
{
    use ExpenseSourceRules;

    public function authorize(): bool
    {
        return $this->user()?->can('disbursement.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disbursement_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payee' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            ...$this->sourceRules(required: true),
        ];
    }

    /** @return array<string, mixed> */
    public function expenseData(): array
    {
        return $this->safe()->except('sources');
    }

    /** @return array<int, array<string, mixed>> */
    public function sources(): array
    {
        return $this->validated('sources');
    }
}
