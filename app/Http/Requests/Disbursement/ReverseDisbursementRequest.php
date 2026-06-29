<?php

namespace App\Http\Requests\Disbursement;

use Illuminate\Foundation\Http\FormRequest;

class ReverseDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reverse', $this->route('disbursement')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
