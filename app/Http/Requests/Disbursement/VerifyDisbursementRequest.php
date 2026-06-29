<?php

namespace App\Http\Requests\Disbursement;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verify', $this->route('disbursement')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
