<?php

namespace App\Domains\Expense\DTOs;

use App\Models\User;

readonly class UpdateExpenseDto
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, ExpenseFundSourceDto|array<string, mixed>>|null  $sources
     */
    public function __construct(
        public array $data,
        public ?array $sources,
        public User $actor,
    ) {}
}
