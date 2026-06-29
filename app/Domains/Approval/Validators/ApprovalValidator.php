<?php

namespace App\Domains\Approval\Validators;

use App\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Model;

class ApprovalValidator
{
    public function assertApprovable(Model $entity): void
    {
        if (! method_exists($entity, 'approvals')) {
            throw new DomainException('Entitas tidak mendukung workflow approval.');
        }
    }
}
