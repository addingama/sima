<?php

namespace App\Http\Requests\BankFee;

use Illuminate\Foundation\Http\FormRequest;

class ReverseBankFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reverse', $this->route('bankFee')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
