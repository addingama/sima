<?php

namespace App\Http\Requests\GrantApplication;

use App\Http\Requests\Concerns\ExpenseSourceRules;
use Illuminate\Foundation\Http\FormRequest;

class CreateGrantDisbursementRequest extends FormRequest
{
    use ExpenseSourceRules;

    public function authorize(): bool
    {
        $grant = $this->route('grant_application');

        return $grant !== null && ($this->user()?->can('createDisbursement', $grant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disbursement_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
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
