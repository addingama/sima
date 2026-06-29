<?php

namespace App\Policies;

use App\Models\OperationalLiability;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class OperationalLiabilityPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'liability.view');
    }

    public function view(User $user, OperationalLiability $operationalLiability): bool
    {
        return $this->allows($user, 'liability.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'liability.manage');
    }

    public function update(User $user, OperationalLiability $operationalLiability): bool
    {
        return $this->allows($user, 'liability.manage');
    }
}
