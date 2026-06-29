<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat langkah workflow approval (submit/verify/approve/reject/post/reverse)
 * pada transaksi apa pun + meneruskan ke audit log.
 *
 * Model transaksi memakai trait HasApprovals (relasi morphMany approvals).
 */
class ApprovalService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function record(Model $entity, ApprovalAction $action, ?User $actor, ?string $notes = null): Approval
    {
        /** @var Approval $approval */
        $approval = $entity->approvals()->create([
            'action' => $action->value,
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->getRoleNames()->first(),
            'notes' => $notes,
            'acted_at' => now(),
        ]);

        $this->audit->log(
            $entity,
            $action->value,
            null,
            ['notes' => $notes],
            $actor,
            'approval'
        );

        return $approval;
    }
}
