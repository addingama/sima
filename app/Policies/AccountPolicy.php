<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class AccountPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'account.view');
    }

    public function view(User $user, Account $account): bool
    {
        return $this->allows($user, 'account.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'account.manage');
    }

    public function update(User $user, Account $account): bool
    {
        return $this->allows($user, 'account.manage');
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->allows($user, 'account.manage');
    }
}
