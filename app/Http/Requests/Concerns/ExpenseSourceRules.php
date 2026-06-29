<?php

namespace App\Http\Requests\Concerns;

trait ExpenseSourceRules
{
    /** @return array<string, mixed> */
    protected function sourceRules(bool $required = true): array
    {
        $listRule = $required ? 'required' : 'sometimes';

        return [
            'sources' => [$listRule, 'array', 'min:1'],
            'sources.*.fund_id' => ['required', 'exists:funds,id'],
            'sources.*.program_id' => ['nullable', 'exists:programs,id'],
            'sources.*.amount' => ['required', 'numeric', 'gt:0'],
            'sources.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
