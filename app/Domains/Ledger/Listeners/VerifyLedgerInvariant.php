<?php

namespace App\Domains\Ledger\Listeners;

use App\Domains\Ledger\Events\LedgerJournalPosted;

/** Placeholder — siap diperluas untuk invariant monitoring. */
class VerifyLedgerInvariant
{
    public function handle(LedgerJournalPosted $event): void
    {
        // Invariant dicek saat posting via LedgerService.
    }
}
