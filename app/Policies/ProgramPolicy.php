<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class ProgramPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'program.view');
    }

    public function view(User $user, Program $program): bool
    {
        return $this->allows($user, 'program.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'program.manage');
    }

    public function update(User $user, Program $program): bool
    {
        return $this->allows($user, 'program.manage');
    }

    public function delete(User $user, Program $program): bool
    {
        return $this->allows($user, 'program.manage');
    }
}
