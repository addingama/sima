<?php

namespace App\Domains\Grant\Policies;

use App\Domains\Shared\Concerns\ChecksSimaPermission;
use App\Enums\GrantApplicationStatus;
use App\Models\GrantApplication;
use App\Models\User;

class GrantApplicationPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'grant.view');
    }

    public function view(User $user, GrantApplication $grant): bool
    {
        if (! $this->allows($user, 'grant.view')) {
            return false;
        }

        if ($this->seesAll($user)) {
            return true;
        }

        return $this->isCreator($user, $grant) || $this->isAssignedVerifier($user, $grant);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'grant.create');
    }

    public function update(User $user, GrantApplication $grant): bool
    {
        if (! $this->allows($user, 'grant.update')) {
            return false;
        }

        if (! in_array($grant->status, [GrantApplicationStatus::DRAFT, GrantApplicationStatus::VERIFICATION], true)) {
            return false;
        }

        if ($grant->status === GrantApplicationStatus::DRAFT) {
            return $this->isCreator($user, $grant) || $this->seesAll($user);
        }

        return $this->isAssignedVerifier($user, $grant) || $this->seesAll($user);
    }

    public function assign(User $user, GrantApplication $grant): bool
    {
        if (! $this->allows($user, 'grant.assign')) {
            return false;
        }

        if (! in_array($grant->status, [GrantApplicationStatus::DRAFT, GrantApplicationStatus::VERIFICATION], true)) {
            return false;
        }

        return $this->seesAll($user) || $this->isCreator($user, $grant);
    }

    public function sendToVerification(User $user, GrantApplication $grant): bool
    {
        if ($grant->status !== GrantApplicationStatus::DRAFT) {
            return false;
        }

        return $this->isCreator($user, $grant) || $this->assign($user, $grant);
    }

    public function verify(User $user, GrantApplication $grant): bool
    {
        if ($grant->status !== GrantApplicationStatus::VERIFICATION) {
            return false;
        }

        if (! $this->allows($user, 'grant.verify')) {
            return false;
        }

        return $this->isAssignedVerifier($user, $grant) || $this->seesAll($user);
    }

    public function approve(User $user, GrantApplication $grant): bool
    {
        return $this->allows($user, 'grant.approve')
            && $grant->status === GrantApplicationStatus::PENDING_APPROVAL;
    }

    public function reject(User $user, GrantApplication $grant): bool
    {
        return $this->approve($user, $grant);
    }

    public function returnToVerification(User $user, GrantApplication $grant): bool
    {
        if ($grant->status !== GrantApplicationStatus::PENDING_APPROVAL) {
            return false;
        }

        if ($this->allows($user, 'grant.approve')) {
            return true;
        }

        return $this->allows($user, 'grant.verify') && $this->isAssignedVerifier($user, $grant);
    }

    public function complete(User $user, GrantApplication $grant): bool
    {
        return $this->allows($user, 'grant.handover')
            && $grant->status === GrantApplicationStatus::APPROVED;
    }

    private function seesAll(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'ketua', 'bendahara', 'auditor']);
    }

    private function isCreator(User $user, GrantApplication $grant): bool
    {
        return (int) $grant->created_by === (int) $user->getKey();
    }

    private function isAssignedVerifier(User $user, GrantApplication $grant): bool
    {
        return $grant->assigned_verifier_id !== null
            && (int) $grant->assigned_verifier_id === (int) $user->getKey();
    }
}
