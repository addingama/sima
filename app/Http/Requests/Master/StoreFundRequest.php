<?php

namespace App\Http\Requests\Master;

use App\Models\Fund;
use App\Rules\UniqueActiveCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Fund::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', new UniqueActiveCode('funds')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:restricted,unrestricted'],
            'is_active' => ['boolean'],
        ];
    }
}
