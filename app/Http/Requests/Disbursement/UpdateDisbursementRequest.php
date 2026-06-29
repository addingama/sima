<?php

namespace App\Http\Requests\Disbursement;

use App\Enums\DisbursementStatus;
use App\Http\Requests\Concerns\ExpenseSourceRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDisbursementRequest extends FormRequest
{
    use ExpenseSourceRules;

    public function authorize(): bool
    {
        $expense = $this->route('disbursement');

        return $this->user()?->can('disbursement.create')
            && $expense?->status === DisbursementStatus::DRAFT;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disbursement_date' => ['sometimes', 'date'],
            'account_id' => ['sometimes', 'exists:accounts,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'payee' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            ...$this->sourceRules(required: false),
        ];
    }

    /** @return array<string, mixed> */
    public function expenseData(): array
    {
        return $this->safe()->except('sources');
    }

    /** @return array<int, array<string, mixed>>|null */
    public function sources(): ?array
    {
        return $this->has('sources') ? $this->validated('sources') : null;
    }
}
