<?php

namespace App\Domains\Receipt\DTOs;

use App\Models\Receipt;
use App\Models\User;

readonly class ReverseReceiptDto
{
    public function __construct(
        public Receipt $receipt,
        public User $actor,
        public string $reason,
    ) {}
}
