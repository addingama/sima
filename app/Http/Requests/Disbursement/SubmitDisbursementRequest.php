<?php

namespace App\Http\Requests\Disbursement;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDisbursementRequest extends FormRequest
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
