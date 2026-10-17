<?php

namespace App\Http\Requests\PettyCash;

use App\Models\PettyCashFloat;
use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PettyCashFloat $float */
        $float = $this->route('pettyCashFloat');

        return $this->user()->can('manageLines', $float);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:petty_cash_categories,id'],
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => ['required', 'string', 'max:500'],
        ];
    }
}
