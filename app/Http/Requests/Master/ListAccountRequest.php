<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAccountRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['name', 'code', 'type', 'created_at']), [
            'type' => ['nullable', 'string', Rule::in(['cash', 'bank'])],
        ]);
    }
}
