<?php

namespace App\Domains\Expense\Events;

use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ExpenseSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly Disbursement $expense,
        public readonly User $actor,
    ) {}
}
