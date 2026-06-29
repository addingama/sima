<?php

namespace App\Domains\Audit\Listeners;

use App\Domains\Audit\Events\AuditLogged;

/** Placeholder listener — siap diperluas untuk monitoring/metrics audit. */
class RecordAuditMetrics
{
    public function handle(AuditLogged $event): void
    {
        // No-op: audit sudah tersimpan via repository.
    }
}
