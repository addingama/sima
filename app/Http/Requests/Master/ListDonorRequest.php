<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListDonorRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['name', 'code', 'created_at']), [
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
