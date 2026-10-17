<?php

namespace App\Http\Requests\PettyCash;

use App\Models\PettyCashFloat;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePettyCashLineRequest extends FormRequest
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
            'category_id' => ['sometimes', 'integer', 'exists:petty_cash_categories,id'],
            'spent_on' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => ['sometimes', 'string', 'max:500'],
        ];
    }
}
