<?php

namespace App\Domains\Reconciliation\Policies;

use App\Domains\Shared\Concerns\ChecksSimaPermission;
use App\Models\BankReconciliation;
use App\Models\User;

class ReconciliationPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'reconciliation.view');
    }

    public function view(User $user, BankReconciliation $bankReconciliation): bool
    {
        return $this->allows($user, 'reconciliation.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'reconciliation.manage');
    }

    public function update(User $user, BankReconciliation $bankReconciliation): bool
    {
        return $this->allows($user, 'reconciliation.manage')
            && $bankReconciliation->status === 'draft';
    }

    public function complete(User $user, BankReconciliation $bankReconciliation): bool
    {
        return $this->allows($user, 'reconciliation.manage')
            && $bankReconciliation->status === 'draft';
    }
}
