<?php

namespace App\Policies;

use App\Models\OpeningBalanceBatch;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class OpeningBalanceBatchPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'opening.view');
    }

    public function view(User $user, OpeningBalanceBatch $openingBalanceBatch): bool
    {
        return $this->allows($user, 'opening.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'opening.manage');
    }
}
