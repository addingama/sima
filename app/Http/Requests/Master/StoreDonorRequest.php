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
            'user_id' => ['nullable', 'exists:users,id', 'unique:donors,user_id'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:50', 'required_without:email'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required_without' => 'Isi email atau nomor HP agar akun portal donatur bisa dibuat otomatis.',
            'phone.required_without' => 'Isi email atau nomor HP agar akun portal donatur bisa dibuat otomatis.',
        ];
    }
}
