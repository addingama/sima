<?php

namespace App\Http\Requests\Liability;

use App\Models\OperationalLiability;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationalLiabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OperationalLiability $operationalLiability */
        $operationalLiability = $this->route('operationalLiability');

        return $this->user()->can('update', $operationalLiability);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'liability_date' => ['sometimes', 'date'],
            'creditor' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fund_id' => ['nullable', 'exists:funds,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
