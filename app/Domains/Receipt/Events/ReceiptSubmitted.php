<?php

namespace App\Domains\Receipt\Events;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ReceiptSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly Receipt $receipt,
        public readonly User $actor,
    ) {}
}
