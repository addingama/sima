<?php

namespace App\Http\Requests\Master;

use App\Models\Fund;
use App\Rules\UniqueActiveCode;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Fund $fund */
        $fund = $this->route('fund');

        return $this->user()->can('update', $fund);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Fund $fund */
        $fund = $this->route('fund');

        return [
            'code' => ['sometimes', 'string', 'max:50', new UniqueActiveCode('funds', $fund->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:restricted,unrestricted'],
            'is_active' => ['boolean'],
        ];
    }
}
