<?php

namespace App\Domains\PettyCash\Policies;

use App\Domains\Shared\Concerns\ChecksSimaPermission;
use App\Enums\PettyCashFloatStatus;
use App\Models\PettyCashFloat;
use App\Models\User;

class PettyCashFloatPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'petty_cash.view');
    }

    public function view(User $user, PettyCashFloat $float): bool
    {
        if (! $this->allows($user, 'petty_cash.view')) {
            return false;
        }

        return $this->seesAll($user) || $this->isCustodian($user, $float);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'petty_cash.create');
    }

    public function update(User $user, PettyCashFloat $float): bool
    {
        if (! $this->allows($user, 'petty_cash.update')) {
            return false;
        }

        if (! in_array($float->status, [PettyCashFloatStatus::DRAFT, PettyCashFloatStatus::REPORTING], true)) {
            return false;
        }

        if ($this->seesAll($user)) {
            return true;
        }

        return $this->isCustodian($user, $float) && $float->status === PettyCashFloatStatus::REPORTING;
    }

    public function manageLines(User $user, PettyCashFloat $float): bool
    {
        if ($float->status !== PettyCashFloatStatus::REPORTING) {
            return false;
        }

        if (! $this->allows($user, 'petty_cash.update')) {
            return false;
        }

        return $this->seesAll($user) || $this->isCustodian($user, $float);
    }

    public function close(User $user, PettyCashFloat $float): bool
    {
        if ($float->status !== PettyCashFloatStatus::REPORTING) {
            return false;
        }

        if (! $this->allows($user, 'petty_cash.close')) {
            return false;
        }

        return $this->seesAll($user) || $this->isCustodian($user, $float);
    }

    public function fund(User $user, PettyCashFloat $float): bool
    {
        return $this->allows($user, 'petty_cash.fund')
            && in_array($float->status, [PettyCashFloatStatus::DRAFT, PettyCashFloatStatus::REPORTING], true);
    }

    public function reopen(User $user, PettyCashFloat $float): bool
    {
        return $this->allows($user, 'petty_cash.fund')
            && $float->status === PettyCashFloatStatus::CLOSED
            && $this->seesAll($user);
    }

    public function cancel(User $user, PettyCashFloat $float): bool
    {
        return $this->allows($user, 'petty_cash.create')
            && in_array($float->status, [PettyCashFloatStatus::DRAFT, PettyCashFloatStatus::REPORTING], true)
            && $this->seesAll($user);
    }

    private function seesAll(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'bendahara', 'asisten_bendahara', 'auditor', 'ketua']);
    }

    private function isCustodian(User $user, PettyCashFloat $float): bool
    {
        return (int) $float->custodian_user_id === (int) $user->getKey();
    }
}
