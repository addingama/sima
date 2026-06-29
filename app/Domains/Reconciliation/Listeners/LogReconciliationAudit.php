<?php

namespace App\Domains\Reconciliation\Listeners;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Reconciliation\Events\ReconciliationCompleted;
use App\Domains\Reconciliation\Events\ReconciliationCreated;

class LogReconciliationAudit
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handleCreated(ReconciliationCreated $event): void
    {
        $this->audit->log(
            $event->reconciliation,
            'created',
            null,
            $event->auditPayload,
            $event->actor,
        );
    }

    public function handleCompleted(ReconciliationCompleted $event): void
    {
        $this->audit->log(
            $event->reconciliation,
            'completed',
            null,
            $event->auditPayload,
            $event->actor,
        );
    }
}
