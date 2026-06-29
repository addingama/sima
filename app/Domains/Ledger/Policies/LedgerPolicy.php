<?php

namespace App\Domains\Ledger\Policies;

use App\Domains\Shared\Concerns\ChecksSimaPermission;
use App\Models\User;

class LedgerPolicy
{
    use ChecksSimaPermission;

    public function viewBalances(User $user): bool
    {
        return $this->allows($user, 'report.view')
            || $this->allows($user, 'account.view')
            || $this->allows($user, 'fund.view');
    }
}
