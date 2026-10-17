<?php

namespace App\Http\Requests\PettyCash;

use App\Http\Requests\Concerns\ExpenseSourceRules;
use App\Models\PettyCashFloat;
use Illuminate\Foundation\Http\FormRequest;

class CreatePettyCashDisbursementRequest extends FormRequest
{
    use ExpenseSourceRules;

    public function authorize(): bool
    {
        /** @var PettyCashFloat $float */
        $float = $this->route('pettyCashFloat');

        return $this->user()->can('fund', $float);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'disbursement_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'payee' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            ...$this->sourceRules(required: true),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->sourceMessages();
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
