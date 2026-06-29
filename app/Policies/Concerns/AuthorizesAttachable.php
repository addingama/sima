<?php

namespace App\Policies\Concerns;

use App\Models\BankFee;
use App\Models\Disbursement;
use App\Models\OperationalLiability;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesAttachable
{
    protected function canViewAttachable(User $user, Model $attachable): bool
    {
        return match (true) {
            $attachable instanceof Receipt => $user->can('view', $attachable),
            $attachable instanceof Disbursement => $user->can('view', $attachable),
            $attachable instanceof BankFee => $user->can('view', $attachable),
            $attachable instanceof OperationalLiability => $user->can('view', $attachable),
            default => false,
        };
    }
}
