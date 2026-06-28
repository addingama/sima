<?php

namespace App\Models\Concerns;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasLedgerEntries
{
    /** Semua ledger entry yang bersumber dari transaksi ini. */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }
}
