<?php

namespace App\Http\Requests\Transfer;

use App\Models\AccountTransfer;
use Illuminate\Foundation\Http\FormRequest;

class ReverseAccountTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AccountTransfer $accountTransfer */
        $accountTransfer = $this->route('accountTransfer');

        return $this->user()->can('reverse', $accountTransfer);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
