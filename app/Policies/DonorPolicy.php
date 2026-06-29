<?php

namespace App\Policies;

use App\Models\Donor;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class DonorPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'donor.view');
    }

    public function view(User $user, Donor $donor): bool
    {
        return $this->allows($user, 'donor.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'donor.manage');
    }

    public function update(User $user, Donor $donor): bool
    {
        return $this->allows($user, 'donor.manage');
    }

    public function delete(User $user, Donor $donor): bool
    {
        return $this->allows($user, 'donor.manage');
    }
}
