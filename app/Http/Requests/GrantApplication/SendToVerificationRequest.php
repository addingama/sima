<?php

namespace App\Http\Requests\GrantApplication;

use Illuminate\Foundation\Http\FormRequest;

class SendToVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grant = $this->route('grant_application');

        return $grant !== null && ($this->user()?->can('sendToVerification', $grant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
