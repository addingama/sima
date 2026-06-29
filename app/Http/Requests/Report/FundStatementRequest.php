<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class FundStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fund_id' => ['required', 'integer', 'exists:funds,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
