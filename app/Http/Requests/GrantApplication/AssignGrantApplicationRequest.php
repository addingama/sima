<?php

namespace App\Http\Requests\GrantApplication;

use Illuminate\Foundation\Http\FormRequest;

class AssignGrantApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grant = $this->route('grant_application');

        return $grant !== null && ($this->user()?->can('assign', $grant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assigned_verifier_id' => ['sometimes', 'required_without:assigned_handover_id', 'integer', 'exists:users,id'],
            'assigned_handover_id' => ['sometimes', 'required_without:assigned_verifier_id', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
