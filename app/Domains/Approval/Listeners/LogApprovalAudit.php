<?php

namespace App\Domains\Approval\Listeners;

use App\Domains\Approval\Events\ApprovalRecorded;
use App\Domains\Audit\Services\AuditLogService;

class LogApprovalAudit
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(ApprovalRecorded $event): void
    {
        $this->audit->log(
            $event->dto->entity,
            $event->dto->action->value,
            null,
            ['notes' => $event->dto->notes],
            $event->dto->actor,
            'approval',
        );
    }
}
