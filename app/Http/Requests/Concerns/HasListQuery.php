<?php

namespace App\Http\Requests\Concerns;

use App\Support\Query\ListQueryDto;
use Illuminate\Validation\Rule;

trait HasListQuery
{
    /** @param  array<int, string>  $sortable */
    protected function listQueryRules(array $sortable = [], bool $withCursor = false): array
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in($sortable)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];

        if ($withCursor) {
            $rules['cursor'] = ['nullable', 'string'];
        }

        return $rules;
    }

    public function listQuery(int $defaultPerPage = 15): ListQueryDto
    {
        return ListQueryDto::fromValidated($this->validated(), $defaultPerPage);
    }
}
