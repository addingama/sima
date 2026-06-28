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
 * Bertanggung jawab atas:
 *  - Menghitung saldo (sumber kebenaran = SUM ledger_entries.amount).
 *  - Memposting ledger entry secara atomik.
 *  - Menjamin invariant: saldo akun & saldo dana tidak boleh negatif.
 *  - Reversal (negasi) untuk pembatalan transaksi yang sudah ter-post.
 */
class LedgerService
{
    public function balanceForFund(int $fundId): string
    {
        return $this->normalize((string) (LedgerEntry::where('fund_id', $fundId)->sum('amount') ?? '0'));
    }

    public function balanceForAccount(int $accountId): string
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
            $entries = collect();
            $accountIds = [];
            $fundIds = [];

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
                $accountIds[$leg['account_id']] = true;
                $fundIds[$leg['fund_id']] = true;
            }

            $this->assertNonNegativeBalances(array_keys($accountIds), array_keys($fundIds));

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
     * @param  array<int, int>  $accountIds
     * @param  array<int, int>  $fundIds
     */
    public function assertNonNegativeBalances(array $accountIds, array $fundIds): void
    {
        foreach ($fundIds as $fundId) {
            $balance = $this->balanceForFund($fundId);
            if (bccomp($balance, '0', 2) < 0) {
                $fund = Fund::find($fundId);
                throw InsufficientBalanceException::fund(
                    $fund?->name ?? "#{$fundId}",
                    '0',
                    bcmul($balance, '-1', 2)
                );
            }
        }

        foreach ($accountIds as $accountId) {
            $balance = $this->balanceForAccount($accountId);
            if (bccomp($balance, '0', 2) < 0) {
                $account = Account::find($accountId);
                throw InsufficientBalanceException::account(
                    $account?->name ?? "#{$accountId}",
                    '0',
                    bcmul($balance, '-1', 2)
                );
            }
        }
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
