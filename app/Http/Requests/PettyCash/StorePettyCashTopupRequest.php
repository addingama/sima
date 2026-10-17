<?php

namespace App\Http\Requests\PettyCash;

use App\Models\PettyCashFloat;
use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PettyCashFloat $float */
        $float = $this->route('pettyCashFloat');

        return $this->user()->can('view', $float)
            && $this->user()->hasPermissionTo('petty_cash.topup_request');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
