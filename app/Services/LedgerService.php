<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lapisan inti buku besar.
 *
 * Source of truth = ledger_entries (append-only/immutable).
 * Saldo termaterialisasi (account_balances/fund_balances) adalah CACHE + titik
 * serialisasi (lockForUpdate) agar invariant "saldo tidak boleh negatif" AMAN dari
 * race condition pada transaksi konkuren, sekaligus pembacaan saldo O(1).
 *
 * Aturan anti-deadlock: penguncian SELALU berurutan — fund (asc id) lalu account (asc id).
 */
class LedgerService
{
    /** Saldo dana (cache O(1)). */
    public function balanceForFund(int $fundId): string
    {
        $value = DB::table('fund_balances')->where('fund_id', $fundId)->value('balance');

        return $this->normalize((string) ($value ?? '0'));
    }

    /** Saldo akun (cache O(1)). */
    public function balanceForAccount(int $accountId): string
    {
        $value = DB::table('account_balances')->where('account_id', $accountId)->value('balance');

        return $this->normalize((string) ($value ?? '0'));
    }

    /** Saldo dana dihitung ulang dari source of truth (untuk drift-check/rebuild). */
    public function ledgerSumForFund(int $fundId): string
    {
        return $this->normalize((string) (LedgerEntry::where('fund_id', $fundId)->sum('amount') ?? '0'));
    }

    /** Saldo akun dihitung ulang dari source of truth (untuk drift-check/rebuild). */
    public function ledgerSumForAccount(int $accountId): string
    {
        return $this->normalize((string) (LedgerEntry::where('account_id', $accountId)->sum('amount') ?? '0'));
    }

    /**
     * Posting sekumpulan "leg" ledger dalam satu transaksi atomik.
     * Setiap leg: [account_id, fund_id, amount(signed string), type(LedgerType),
     *              source(Model)?, program_id?, memo?, entry_date?, reversal_of_id?].
     *
     * @param  array<int, array<string, mixed>>  $legs
     * @return Collection<int, LedgerEntry>
     */
    public function post(array $legs, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($legs, $actor): Collection {
            // Akumulasi delta saldo per akun & per dana.
            $accountDeltas = [];
            $fundDeltas = [];
            foreach ($legs as $leg) {
                $amount = (string) $leg['amount'];
                $accountDeltas[(int) $leg['account_id']] = bcadd($accountDeltas[(int) $leg['account_id']] ?? '0', $amount, 2);
                $fundDeltas[(int) $leg['fund_id']] = bcadd($fundDeltas[(int) $leg['fund_id']] ?? '0', $amount, 2);
            }

            // Kunci & terapkan saldo (urutan tetap: fund lalu account, ascending) -> hindari deadlock.
            ksort($fundDeltas);
            foreach ($fundDeltas as $fundId => $delta) {
                $this->applyDelta('fund_balances', 'fund_id', $fundId, $delta, 'fund');
            }
            ksort($accountDeltas);
            foreach ($accountDeltas as $accountId => $delta) {
                $this->applyDelta('account_balances', 'account_id', $accountId, $delta, 'account');
            }

            // Tulis entri ledger (source of truth).
            $entries = collect();
            foreach ($legs as $leg) {
                /** @var Model|null $source */
                $source = $leg['source'] ?? null;
                $type = $leg['type'] instanceof LedgerType ? $leg['type'] : LedgerType::from($leg['type']);

                $entry = new LedgerEntry([
                    'entry_date' => $leg['entry_date'] ?? now()->toDateString(),
                    'account_id' => $leg['account_id'],
                    'fund_id' => $leg['fund_id'],
                    'program_id' => $leg['program_id'] ?? null,
                    'amount' => $leg['amount'],
                    'type' => $type,
                    'reversal_of_id' => $leg['reversal_of_id'] ?? null,
                    'memo' => $leg['memo'] ?? null,
                    'created_by' => $actor?->getKey(),
                ]);

                if ($source instanceof Model) {
                    $entry->source()->associate($source);
                }

                $entry->save();
                $entries->push($entry);
            }

            return $entries;
        });
    }

    /**
     * Reversal: membuat entry negasi untuk semua entry transaksi sumber yang belum dibalik.
     * Invariant tetap dijaga — bila pembalikan menyebabkan saldo negatif (mis. dana sudah
     * terpakai di hilir), operasi ditolak.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function reverse(Model $source, ?User $actor = null, ?string $memo = null): Collection
    {
        return DB::transaction(function () use ($source, $actor, $memo): Collection {
            $original = LedgerEntry::where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->where('type', '!=', LedgerType::REVERSAL->value)
                ->whereNotIn('id', function ($q) {
                    $q->select('reversal_of_id')->from('ledger_entries')->whereNotNull('reversal_of_id');
                })
                ->get();

            $legs = $original->map(fn (LedgerEntry $e) => [
                'entry_date' => now()->toDateString(),
                'account_id' => $e->account_id,
                'fund_id' => $e->fund_id,
                'program_id' => $e->program_id,
                'amount' => bcmul((string) $e->amount, '-1', 2),
                'type' => LedgerType::REVERSAL,
                'source' => $source,
                'reversal_of_id' => $e->id,
                'memo' => $memo ?? 'Reversal',
            ])->all();

            return $this->post($legs, $actor);
        });
    }

    /**
     * Kunci baris saldo (FOR UPDATE), terapkan delta, tolak bila hasilnya negatif.
     */
    private function applyDelta(string $table, string $key, int $id, string $delta, string $kind): void
    {
        // Pastikan baris ada agar bisa dikunci (idempoten, aman konkuren).
        DB::table($table)->insertOrIgnore([
            $key => $id,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $current = (string) DB::table($table)->where($key, $id)->lockForUpdate()->value('balance');
        $new = bcadd($current, $delta, 2);

        if (bccomp($new, '0', 2) < 0) {
            $requested = bcmul($delta, '-1', 2);
            if ($kind === 'fund') {
                throw InsufficientBalanceException::fund(Fund::find($id)?->name ?? "#{$id}", $current, $requested);
            }
            throw InsufficientBalanceException::account(Account::find($id)?->name ?? "#{$id}", $current, $requested);
        }

        DB::table($table)->where($key, $id)->update([
            'balance' => $new,
            'updated_at' => now(),
        ]);
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
