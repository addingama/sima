<?php

namespace App\Http\Requests\GrantApplication;

use Illuminate\Foundation\Http\FormRequest;

class ReturnGrantApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grant = $this->route('grant_application');

        return $grant !== null && ($this->user()?->can('returnToVerification', $grant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
