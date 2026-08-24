<?php

namespace App\Http\Requests\Transfer;

use App\Models\FundTransfer;
use Illuminate\Foundation\Http\FormRequest;

class PostFundTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FundTransfer $fundTransfer */
        $fundTransfer = $this->route('fundTransfer');

        return $this->user()->can('post', $fundTransfer);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
