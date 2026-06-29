<?php

namespace App\Http\Requests\Liability;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListOperationalLiabilityRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['liability_date', 'liability_number', 'amount', 'created_at']), [
            'status' => ['nullable', 'string'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }
}
