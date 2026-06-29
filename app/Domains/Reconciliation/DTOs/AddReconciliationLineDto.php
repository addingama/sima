<?php

namespace App\Domains\Reconciliation\DTOs;

use App\Models\BankReconciliation;

readonly class AddReconciliationLineDto
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public BankReconciliation $reconciliation,
        public array $data,
    ) {}
}
