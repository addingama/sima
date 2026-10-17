<?php

namespace App\Http\Requests\PettyCash;

use App\Enums\PettyCashFloatPurpose;
use App\Http\Requests\Concerns\HasListQuery;
use App\Models\PettyCashFloat;
use App\Support\Query\ListQueryDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPettyCashFloatRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', PettyCashFloat::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mine')) {
            $this->merge([
                'mine' => filter_var($this->input('mine'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules([
            'created_at', 'float_number', 'status', 'purpose', 'period_start',
        ]), [
            'status' => ['sometimes', 'string'],
            'purpose' => ['sometimes', Rule::enum(PettyCashFloatPurpose::class)],
            'custodian_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'program_id' => ['sometimes', 'integer', 'exists:programs,id'],
            'mine' => ['sometimes', 'boolean'],
        ]);
    }

    public function listQuery(): ListQueryDto
    {
        return ListQueryDto::fromValidated($this->validated());
    }
}
