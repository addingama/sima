<?php

namespace App\Domains\Shared\Concerns;

use App\Models\User;

trait ChecksSimaPermission
{
    protected function allows(User $user, string $permission): bool
    {
        return $user->can($permission);
    }
}
