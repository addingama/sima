<?php

namespace App\Policies;

use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class ReceiptPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'receipt.view');
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $this->allows($user, 'receipt.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'receipt.create');
    }

    public function update(User $user, Receipt $receipt): bool
    {
        return $this->allows($user, 'receipt.create')
            && $receipt->status === ReceiptStatus::DRAFT;
    }

    public function submit(User $user, Receipt $receipt): bool
    {
        return $this->allows($user, 'receipt.submit')
            && $receipt->status === ReceiptStatus::DRAFT;
    }

    public function approve(User $user, Receipt $receipt): bool
    {
        return $this->allows($user, 'receipt.approve')
            && $receipt->status === ReceiptStatus::SUBMITTED;
    }

    public function reject(User $user, Receipt $receipt): bool
    {
        return $this->allows($user, 'receipt.reject')
            && $receipt->status === ReceiptStatus::SUBMITTED;
    }

    public function reverse(User $user, Receipt $receipt): bool
    {
        return $this->allows($user, 'receipt.reverse')
            && $receipt->status === ReceiptStatus::APPROVED;
    }
}
