<?php

namespace App\Domains\Approval\Policies;

use App\Domains\Shared\Concerns\ChecksSimaPermission;
use App\Models\User;

class ApprovalPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'receipt.view')
            || $this->allows($user, 'disbursement.view');
    }
}
