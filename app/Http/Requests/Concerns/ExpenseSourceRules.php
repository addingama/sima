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
            'sources.*.fund_id' => ['required', 'distinct', 'exists:funds,id'],
            'sources.*.program_id' => ['nullable', 'exists:programs,id'],
            'sources.*.amount' => ['required', 'numeric', 'gt:0'],
            'sources.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    protected function sourceMessages(): array
    {
        return [
            'sources.*.fund_id.distinct' => 'Dana Amanah pada sumber dana tidak boleh duplikat. Gabungkan nominal pada satu baris.',
        ];
    }
}
