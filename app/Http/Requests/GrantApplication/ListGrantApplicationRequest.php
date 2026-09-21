<?php

namespace App\Http\Requests\GrantApplication;

use App\Enums\GrantApplicationStatus;
use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGrantApplicationRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules([
            'created_at',
            'application_number',
            'recommended_amount',
            'status',
        ]), [
            'status' => ['nullable', Rule::enum(GrantApplicationStatus::class)],
            'assigned_verifier_id' => ['nullable', 'integer', 'exists:users,id'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'in:cash,transfer'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'mine' => ['nullable', 'boolean'],
        ]);
    }
}
