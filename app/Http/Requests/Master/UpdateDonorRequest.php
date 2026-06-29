<?php

namespace App\Http\Requests\Master;

use App\Models\Donor;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Donor $donor */
        $donor = $this->route('donor');

        return $this->user()->can('update', $donor);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Donor $donor */
        $donor = $this->route('donor');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:individu,lembaga'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
