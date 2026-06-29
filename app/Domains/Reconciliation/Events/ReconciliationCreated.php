<?php

namespace App\Domains/Reconciliation\Events;

use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ReconciliationCreated
{
    use Dispatchable;

    /** @param array<string, mixed> $auditPayload */
    public function __construct(
        public readonly BankReconciliation $reconciliation,
        public readonly User $actor,
        public readonly array $auditPayload,
    ) {}
}
