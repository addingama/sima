<?php

namespace App\Http\Requests\GrantApplication;

use Illuminate\Foundation\Http\FormRequest;

class CompleteGrantApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grant = $this->route('grant_application');

        return $grant !== null && ($this->user()?->can('complete', $grant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'handed_over_on' => ['required', 'date'],
        ];
    }
}
