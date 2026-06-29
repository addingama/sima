<?php

namespace App\Http\Requests\Master;

use App\Models\Account;
use App\Rules\UniqueActiveCode;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Account $account */
        $account = $this->route('account');

        return $this->user()->can('update', $account);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Account $account */
        $account = $this->route('account');

        return [
            'code' => ['sometimes', 'string', 'max:50', new UniqueActiveCode('accounts', $account->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:cash,bank'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
