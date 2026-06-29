<?php

namespace App\Domains\Expense\DTOs;

use App\Models\Disbursement;
use App\Models\User;

readonly class ReverseExpenseDto
{
    public function __construct(
        public Disbursement $expense,
        public User $actor,
        public string $reason,
    ) {}
}
