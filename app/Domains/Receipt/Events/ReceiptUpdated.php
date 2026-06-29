<?php

namespace App\Domains\Receipt\Events;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ReceiptUpdated
{
    use Dispatchable;

    /** @param array<string, mixed> $before */
    public function __construct(
        public readonly Receipt $receipt,
        public readonly array $before,
        public readonly User $actor,
    ) {}
}
