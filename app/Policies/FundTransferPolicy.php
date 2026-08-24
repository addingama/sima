<?php

namespace App\Policies;

use App\Enums\AccountTransferStatus;
use App\Models\FundTransfer;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class FundTransferPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'transfer.view');
    }

    public function view(User $user, FundTransfer $fundTransfer): bool
    {
        return $this->allows($user, 'transfer.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'transfer.manage');
    }

    public function post(User $user, FundTransfer $fundTransfer): bool
    {
        return $this->allows($user, 'transfer.post')
            && $fundTransfer->status === AccountTransferStatus::DRAFT;
    }

    public function reverse(User $user, FundTransfer $fundTransfer): bool
    {
        return $this->allows($user, 'transfer.reverse')
            && $fundTransfer->status === AccountTransferStatus::POSTED;
    }
}
