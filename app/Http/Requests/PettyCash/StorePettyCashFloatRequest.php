<?php

namespace App\Http\Requests\PettyCash;

use App\Enums\PettyCashFloatPurpose;
use App\Models\PettyCashFloat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePettyCashFloatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PettyCashFloat::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::enum(PettyCashFloatPurpose::class)],
            'custodian_user_id' => ['required', 'integer', 'exists:users,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
