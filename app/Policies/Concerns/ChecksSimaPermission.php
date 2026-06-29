<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksSimaPermission
{
    protected function allows(User $user, string $permission): bool
    {
        return $user->can($permission);
    }
}
