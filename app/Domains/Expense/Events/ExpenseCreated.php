<?php

namespace App\Domains\Expense\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ExpenseCreated
{
    use Dispatchable;

    public function __construct(
        public readonly \App\Models\Disbursement $expense,
        public readonly User $actor,
    ) {}
}
