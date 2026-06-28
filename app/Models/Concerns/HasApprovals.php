<?php

namespace App\Models\Concerns;

use App\Enums\ApprovalAction;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasApprovals
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->latest('acted_at');
    }

    /** Catat satu langkah approval/workflow pada transaksi ini. */
    public function recordApproval(ApprovalAction $action, ?User $actor, ?string $notes = null): Approval
    {
        return $this->approvals()->create([
            'action' => $action->value,
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->getRoleNames()->first(),
            'notes' => $notes,
            'acted_at' => now(),
        ]);
    }
}
