<?php

namespace App\Domains\PettyCash\Policies;

use App\Domains\Shared\Concerns\ChecksSimaPermission;
use App\Models\PettyCashCategory;
use App\Models\User;

class PettyCashCategoryPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'petty_cash.view')
            || $this->allows($user, 'petty_cash.category.manage');
    }

    public function view(User $user, PettyCashCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'petty_cash.category.manage');
    }

    public function update(User $user, PettyCashCategory $category): bool
    {
        return $this->allows($user, 'petty_cash.category.manage');
    }

    public function delete(User $user, PettyCashCategory $category): bool
    {
        return $this->allows($user, 'petty_cash.category.manage');
    }
}
