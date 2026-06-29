<?php

namespace App\Domains\Expense\Policies;

use App\Domains\Shared\Concerns\ChecksSimaPermission;
use App\Enums\DisbursementStatus;
use App\Models\Disbursement;
use App\Models\User;

class ExpensePolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'disbursement.view');
    }

    public function view(User $user, Disbursement $disbursement): bool
    {
        return $this->allows($user, 'disbursement.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'disbursement.create');
    }

    public function update(User $user, Disbursement $disbursement): bool
    {
        return $this->allows($user, 'disbursement.create')
            && $disbursement->status === DisbursementStatus::DRAFT;
    }

    public function submit(User $user, Disbursement $disbursement): bool
    {
        return $this->allows($user, 'disbursement.submit')
            && $disbursement->status === DisbursementStatus::DRAFT;
    }

    public function verify(User $user, Disbursement $disbursement): bool
    {
        return $this->allows($user, 'disbursement.verify')
            && $disbursement->status === DisbursementStatus::SUBMITTED;
    }

    public function approve(User $user, Disbursement $disbursement): bool
    {
        return $this->allows($user, 'disbursement.approve')
            && $disbursement->status === DisbursementStatus::VERIFIED;
    }

    public function reject(User $user, Disbursement $disbursement): bool
    {
        return $this->allows($user, 'disbursement.reject')
            && in_array($disbursement->status, [DisbursementStatus::SUBMITTED, DisbursementStatus::VERIFIED], true);
    }

    public function reverse(User $user, Disbursement $disbursement): bool
    {
        return $this->allows($user, 'disbursement.reverse')
            && $disbursement->status === DisbursementStatus::APPROVED;
    }
}
