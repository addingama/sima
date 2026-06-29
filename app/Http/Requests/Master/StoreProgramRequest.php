<?php

namespace App\Http\Requests\Master;

use App\Models\Program;
use App\Rules\UniqueActiveCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Program::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fund_id' => ['nullable', 'exists:funds,id'],
            'code' => ['required', 'string', 'max:50', new UniqueActiveCode('programs')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:planned,active,closed'],
            'is_active' => ['boolean'],
        ];
    }
}
