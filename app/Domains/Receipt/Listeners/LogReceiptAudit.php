<?php

namespace App\Domains\Receipt\Listeners;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Receipt\Events\ReceiptCreated;
use App\Domains\Receipt\Events\ReceiptUpdated;

class LogReceiptAudit
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handleCreated(ReceiptCreated $event): void
    {
        $this->audit->log($event->receipt, 'created', null, $event->receipt->toArray(), $event->actor);
    }

    public function handleUpdated(ReceiptUpdated $event): void
    {
        $this->audit->log(
            $event->receipt,
            'updated',
            $event->before,
            $event->receipt->fresh()->toArray(),
            $event->actor,
        );
    }
}
