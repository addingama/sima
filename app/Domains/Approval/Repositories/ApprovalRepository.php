<?php

namespace App\Domains\Approval\Repositories;

use App\Domains\Approval\DTOs\RecordApprovalDto;
use App\Models\Approval;

class ApprovalRepository
{
    public function create(RecordApprovalDto $dto): Approval
    {
        /** @var Approval $approval */
        $approval = $dto->entity->approvals()->create([
            'action' => $dto->action->value,
            'actor_id' => $dto->actor?->getKey(),
            'actor_role' => $dto->actor?->getRoleNames()->first(),
            'notes' => $dto->notes,
            'acted_at' => now(),
        ]);

        return $approval;
    }
}
