<?php

namespace App\Http\Requests\Transfer;

use App\Models\AccountTransfer;
use Illuminate\Foundation\Http\FormRequest;

class PostAccountTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AccountTransfer $accountTransfer */
        $accountTransfer = $this->route('accountTransfer');

        return $this->user()->can('post', $accountTransfer);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
