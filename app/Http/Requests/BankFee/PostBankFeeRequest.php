<?php

namespace App\Http\Requests\BankFee;

use Illuminate\Foundation\Http\FormRequest;

class PostBankFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
