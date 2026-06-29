<?php

namespace App\Domains\Audit\Policies;

use App\Domains\Shared\Concerns\ChecksSimaPermission;
use App\Models\User;
use OwenIt\Auditing\Models\Audit;

class AuditPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'audit.view');
    }

    public function view(User $user, Audit $audit): bool
    {
        return $this->allows($user, 'audit.view');
    }
}
