<?php

namespace App\Domains\Expense\Events;

use App\Models\BankFee;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class BankFeeCreated
{
    use Dispatchable;

    public function __construct(
        public readonly BankFee $fee,
        public readonly User $actor,
    ) {}
}
