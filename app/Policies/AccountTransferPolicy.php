<?php

namespace App\Policies;

use App\Enums\AccountTransferStatus;
use App\Models\AccountTransfer;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class AccountTransferPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'transfer.view');
    }

    public function view(User $user, AccountTransfer $accountTransfer): bool
    {
        return $this->allows($user, 'transfer.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'transfer.manage');
    }

    public function post(User $user, AccountTransfer $accountTransfer): bool
    {
        return $this->allows($user, 'transfer.post')
            && $accountTransfer->status === AccountTransferStatus::DRAFT;
    }

    public function reverse(User $user, AccountTransfer $accountTransfer): bool
    {
        return $this->allows($user, 'transfer.reverse')
            && $accountTransfer->status === AccountTransferStatus::POSTED;
    }
}
