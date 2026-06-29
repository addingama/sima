<?php

namespace App\Policies;

use App\Enums\BankFeeStatus;
use App\Models\BankFee;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class BankFeePolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'bankfee.view');
    }

    public function view(User $user, BankFee $bankFee): bool
    {
        return $this->allows($user, 'bankfee.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'bankfee.manage');
    }

    public function post(User $user, BankFee $bankFee): bool
    {
        return $this->allows($user, 'bankfee.post')
            && $bankFee->status === BankFeeStatus::DRAFT;
    }

    public function reverse(User $user, BankFee $bankFee): bool
    {
        return $this->allows($user, 'bankfee.reverse')
            && $bankFee->status === BankFeeStatus::POSTED;
    }
}
