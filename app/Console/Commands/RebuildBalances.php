<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** @deprecated Saldo materialized dihapus — ledger adalah satu-satunya source of truth. */
class RebuildBalances extends Command
{
    protected $signature = 'sima:rebuild-balances';

    protected $description = 'Tidak diperlukan: saldo dihitung langsung dari ledger_entries.';

    public function handle(): int
    {
        $this->info('Saldo materialized tidak dipakai. Gunakan sima:check-balances untuk verifikasi invariant.');

        return self::SUCCESS;
    }
}
