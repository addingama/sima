<?php

namespace App\Http\Requests\GrantApplication;

use App\Enums\GrantPaymentMethod;
use App\Models\GrantApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrantApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrantApplication::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'recipient_address' => ['nullable', 'string'],
            'recipient_identity_number' => ['nullable', 'string', 'max:32'],
            'recommended_amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string'],
            'recommender_name' => ['required', 'string', 'max:255'],
            'recommender_contact' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::enum(GrantPaymentMethod::class)],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'assigned_verifier_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function grantData(): array
    {
        return $this->validated();
    }
}
