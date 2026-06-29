<?php

namespace App\Http\Requests\Master;

use App\Models\Donor;
use App\Rules\UniqueActiveCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Donor::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', new UniqueActiveCode('donors')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:individu,lembaga'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
