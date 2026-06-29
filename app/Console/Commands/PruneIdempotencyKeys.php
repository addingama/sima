<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Hapus Idempotency-Key yang sudah expired (maintenance). */
class PruneIdempotencyKeys extends Command
{
    protected $signature = 'sima:prune-idempotency {--days=7 : Hapus key expired lebih dari N hari}';

    protected $description = 'Hapus baris idempotency_keys yang sudah expired.';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $deleted = DB::table('idempotency_keys')
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $this->info("Dihapus {$deleted} idempotency key expired (cutoff: {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
