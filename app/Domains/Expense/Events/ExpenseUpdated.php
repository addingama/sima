<?php

namespace App\Domains\Expense\Events;

use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ExpenseUpdated
{
    use Dispatchable;

    /** @param array<string, mixed> $before */
    public function __construct(
        public readonly Disbursement $expense,
        public readonly array $before,
        public readonly User $actor,
    ) {}
}
