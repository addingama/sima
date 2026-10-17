<?php

namespace App\Http\Requests\Report;

use App\Enums\PettyCashFloatPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PettyCashReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('report.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'purpose' => ['nullable', Rule::enum(PettyCashFloatPurpose::class)],
            'status' => ['nullable', 'string'],
            'custodian_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->validated();
    }
}
