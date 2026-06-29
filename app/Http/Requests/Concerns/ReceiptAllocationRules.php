<?php

namespace App\Http\Requests\Concerns;

trait ReceiptAllocationRules
{
    /** @return array<string, mixed> */
    protected function allocationRules(bool $required = true): array
    {
        $listRule = $required ? 'required' : 'sometimes';

        return [
            'allocations' => [$listRule, 'array', 'min:1'],
            'allocations.*.fund_id' => ['required', 'exists:funds,id'],
            'allocations.*.program_id' => ['nullable', 'exists:programs,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
            'allocations.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
