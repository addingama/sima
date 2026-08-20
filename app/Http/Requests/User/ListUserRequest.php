<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListUserRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['name', 'email', 'created_at']), [
            'is_active' => ['nullable', 'boolean'],
            'role' => ['nullable', 'string'],
        ]);
    }
}
