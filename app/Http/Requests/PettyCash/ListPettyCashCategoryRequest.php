<?php

namespace App\Http\Requests\PettyCash;

use App\Http\Requests\Concerns\HasListQuery;
use App\Models\PettyCashCategory;
use App\Support\Query\ListQueryDto;
use Illuminate\Foundation\Http\FormRequest;

class ListPettyCashCategoryRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', PettyCashCategory::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['code', 'name', 'sort_order', 'created_at']), [
            'is_active' => ['sometimes'],
            'active_only' => ['sometimes'],
        ]);
    }

    public function listQuery(): ListQueryDto
    {
        $validated = $this->validated();
        // active_only adalah flag service, bukan kolom — jangan masuk filters ListQueryApplier.
        unset($validated['active_only']);

        return ListQueryDto::fromValidated($validated);
    }

    public function activeOnly(): bool
    {
        return filter_var($this->input('active_only', false), FILTER_VALIDATE_BOOLEAN);
    }
}
