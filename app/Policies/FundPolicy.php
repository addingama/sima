<?php

namespace App\Policies;

use App\Models\Fund;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class FundPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'fund.view');
    }

    public function view(User $user, Fund $fund): bool
    {
        return $this->allows($user, 'fund.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'fund.manage');
    }

    public function update(User $user, Fund $fund): bool
    {
        return $this->allows($user, 'fund.manage') && ! $fund->is_system;
    }

    public function delete(User $user, Fund $fund): bool
    {
        return $this->allows($user, 'fund.manage') && ! $fund->is_system;
    }
}
