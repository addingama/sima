<?php

namespace App\Http\Requests\Master;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Program $program */
        $program = $this->route('program');

        return $this->user()->can('update', $program);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fund_id' => ['nullable', 'exists:funds,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'event_type' => ['nullable', 'in:planned,emergency,campaign,routine'],
            'description' => ['nullable', 'string'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:planned,active,closed'],
            'is_active' => ['boolean'],
        ];
    }
}
