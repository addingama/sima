<?php

namespace App\Http\Requests\PettyCash;

use App\Enums\PettyCashFloatPurpose;
use App\Models\PettyCashFloat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePettyCashFloatRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PettyCashFloat $float */
        $float = $this->route('pettyCashFloat');

        return $this->user()->can('update', $float);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'purpose' => ['sometimes', Rule::enum(PettyCashFloatPurpose::class)],
            'custodian_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
