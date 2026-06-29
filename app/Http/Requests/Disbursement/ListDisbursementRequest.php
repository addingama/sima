<?php

namespace App\Http\Requests\Disbursement;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListDisbursementRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['disbursement_date', 'disbursement_number', 'amount', 'created_at']), [
            'status' => ['nullable', 'string'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }
}
