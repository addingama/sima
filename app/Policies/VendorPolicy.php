<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use App\Policies\Concerns\ChecksSimaPermission;

class VendorPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'vendor.view');
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $this->allows($user, 'vendor.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'vendor.manage');
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $this->allows($user, 'vendor.manage');
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $this->allows($user, 'vendor.manage');
    }
}
