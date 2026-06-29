<?php

namespace App\Domains\Reconciliation\DTOs;

use App\Models\User;

readonly class CreateReconciliationDto
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public User $actor,
    ) {}
}

readonly class AddReconciliationLineDto
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public \App\Models\BankReconciliation $reconciliation,
        public array $data,
    ) {}
}
