<?php

namespace App\Http\Requests\PettyCash;

use App\Models\PettyCashCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePettyCashCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PettyCashCategory $category */
        $category = $this->route('pettyCashCategory');

        return $this->user()->can('update', $category);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var PettyCashCategory $category */
        $category = $this->route('pettyCashCategory');

        return [
            'code' => ['sometimes', 'string', 'max:32', 'alpha_dash', Rule::unique('petty_cash_categories', 'code')->ignore($category->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
