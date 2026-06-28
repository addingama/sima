<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\DisbursementStatus;
use App\Enums\LedgerType;
use App\Exceptions\DomainException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Disbursement;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alur Pengeluaran: draft -> submitted -> verified -> approved (post ke ledger) -> [reversed]
 * Ditolak (rejected) dapat terjadi pada tahap submitted/verified.
 *
 * Pengeluaran dapat ditarik dari SATU atau BEBERAPA Dana Amanah (expense_fund_sources).
 * Total seluruh sumber = disbursements.amount.
 */
class DisbursementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param array<string, mixed> $data Header pengeluaran.
     * @param array<int, array{fund_id:int, amount:string|float, program_id?:int|null, note?:string|null}> $sources
     */
    public function create(array $data, array $sources, User $actor): Disbursement
    {
        if (count($sources) === 0) {
            throw new DomainException('Pengeluaran harus memiliki minimal satu sumber Dana Amanah.');
        }

        $sourcesTotal = '0.00';
        foreach ($sources as $s) {
            if (bccomp((string) $s['amount'], '0', 2) <= 0) {
                throw new DomainException('Nominal tiap sumber dana harus lebih besar dari nol.');
            }
            $sourcesTotal = bcadd($sourcesTotal, (string) $s['amount'], 2);
        }

        $amount = bcadd((string) $data['amount'], '0', 2);
        if (bccomp($sourcesTotal, $amount, 2) !== 0) {
            throw new DomainException(
                "Total sumber dana ({$sourcesTotal}) harus sama dengan nominal pengeluaran ({$amount})."
            );
        }

        return DB::transaction(function () use ($data, $sources, $amount, $actor): Disbursement {
            $disbursement = Disbursement::create([
                ...$data,
                'amount' => $amount,
                'disbursement_number' => $data['disbursement_number'] ?? $this->numbers->next('DSB'),
                'status' => DisbursementStatus::DRAFT->value,
                'created_by' => $actor->getKey(),
            ]);

            $this->syncSources($disbursement, $sources);

            return $disbursement->load('fundSources');
        });
    }

    /**
     * Perbarui pengeluaran draft (termasuk sumber dana).
     *
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>>|null $sources
     */
    public function update(Disbursement $disbursement, array $data, ?array $sources, User $actor): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::DRAFT]);

        return DB::transaction(function () use ($disbursement, $data, $sources): Disbursement {
            $disbursement->update($data);

            if ($sources !== null) {
                $sourcesTotal = array_reduce($sources, fn ($c, $s) => bcadd($c, (string) $s['amount'], 2), '0.00');
                if (bccomp($sourcesTotal, (string) $disbursement->amount, 2) !== 0) {
                    throw new DomainException(
                        "Total sumber dana ({$sourcesTotal}) harus sama dengan nominal pengeluaran ({$disbursement->amount})."
                    );
                }
                $disbursement->fundSources()->delete();
                $this->syncSources($disbursement, $sources);
            }

            return $disbursement->refresh()->load('fundSources');
        });
    }

    public function submit(Disbursement $disbursement, User $actor): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::DRAFT]);
        $this->assertFundsAvailable($disbursement);

        $disbursement->update([
            'status' => DisbursementStatus::SUBMITTED->value,
            'submitted_at' => now(),
            'submitted_by' => $actor->getKey(),
        ]);
        $disbursement->recordApproval(ApprovalAction::SUBMITTED, $actor);

        return $disbursement->refresh();
    }

    public function verify(Disbursement $disbursement, User $actor, ?string $notes = null): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::SUBMITTED]);
        $this->assertFundsAvailable($disbursement);

        $disbursement->update([
            'status' => DisbursementStatus::VERIFIED->value,
            'verified_at' => now(),
            'verified_by' => $actor->getKey(),
        ]);
        $disbursement->recordApproval(ApprovalAction::VERIFIED, $actor, $notes);

        return $disbursement->refresh();
    }

    /** Persetujuan final (Ketua) — sekaligus memposting ke ledger (satu leg per sumber dana). */
    public function approve(Disbursement $disbursement, User $actor, ?string $notes = null): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::VERIFIED]);
        $this->assertFundsAvailable($disbursement);

        return DB::transaction(function () use ($disbursement, $actor, $notes): Disbursement {
            $legs = $disbursement->fundSources->map(fn ($source) => [
                'entry_date' => $disbursement->disbursement_date->toDateString(),
                'account_id' => $disbursement->account_id,
                'fund_id' => $source->fund_id,
                'program_id' => $source->program_id ?? $disbursement->program_id,
                'amount' => bcmul((string) $source->amount, '-1', 2),
                'type' => LedgerType::DISBURSEMENT,
                'source' => $disbursement,
                'memo' => 'Pengeluaran '.$disbursement->disbursement_number,
            ])->all();

            $this->ledger->post($legs, $actor);

            $disbursement->update([
                'status' => DisbursementStatus::APPROVED->value,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
                'posted_at' => now(),
            ]);
            $disbursement->recordApproval(ApprovalAction::APPROVED, $actor, $notes);
            $disbursement->recordApproval(ApprovalAction::POSTED, $actor);

            return $disbursement->refresh();
        });
    }

    public function reject(Disbursement $disbursement, User $actor, string $reason): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::SUBMITTED, DisbursementStatus::VERIFIED]);

        $disbursement->update([
            'status' => DisbursementStatus::REJECTED->value,
            'rejected_at' => now(),
            'rejected_by' => $actor->getKey(),
            'rejection_reason' => $reason,
        ]);
        $disbursement->recordApproval(ApprovalAction::REJECTED, $actor, $reason);

        return $disbursement->refresh();
    }

    /** Reversal pengeluaran yang sudah ter-post (mengembalikan dana ke tiap Dana Amanah & akun). */
    public function reverse(Disbursement $disbursement, User $actor, string $reason): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::APPROVED]);

        return DB::transaction(function () use ($disbursement, $actor, $reason): Disbursement {
            $this->ledger->reverse($disbursement, $actor, 'Reversal pengeluaran: '.$reason);

            $disbursement->update([
                'status' => DisbursementStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);
            $disbursement->recordApproval(ApprovalAction::REVERSED, $actor, $reason);

            return $disbursement->refresh();
        });
    }

    /** @param array<int, array<string, mixed>> $sources */
    private function syncSources(Disbursement $disbursement, array $sources): void
    {
        foreach ($sources as $s) {
            $disbursement->fundSources()->create([
                'fund_id' => $s['fund_id'],
                'program_id' => $s['program_id'] ?? null,
                'amount' => bcadd((string) $s['amount'], '0', 2),
                'note' => $s['note'] ?? null,
            ]);
        }
    }

    /** Validasi saldo TIAP Dana Amanah sumber & saldo akun mencukupi. */
    private function assertFundsAvailable(Disbursement $disbursement): void
    {
        $disbursement->loadMissing('fundSources');

        // Agregasi per fund (bila ada beberapa source ke fund yang sama).
        $perFund = [];
        foreach ($disbursement->fundSources as $source) {
            $perFund[$source->fund_id] = bcadd($perFund[$source->fund_id] ?? '0.00', (string) $source->amount, 2);
        }

        foreach ($perFund as $fundId => $needed) {
            $balance = $this->ledger->balanceForFund((int) $fundId);
            if (bccomp($balance, $needed, 2) < 0) {
                $fund = Fund::find($fundId);
                throw InsufficientBalanceException::fund($fund?->name ?? "#{$fundId}", $balance, $needed);
            }
        }

        $amount = bcadd((string) $disbursement->amount, '0', 2);
        $accountBalance = $this->ledger->balanceForAccount($disbursement->account_id);
        if (bccomp($accountBalance, $amount, 2) < 0) {
            $account = Account::find($disbursement->account_id);
            throw InsufficientBalanceException::account($account?->name ?? '#', $accountBalance, $amount);
        }
    }

    /** @param array<int, DisbursementStatus> $allowed */
    private function assertStatus(Disbursement $disbursement, array $allowed): void
    {
        if (! in_array($disbursement->status, $allowed, true)) {
            $allowedLabels = implode(', ', array_map(fn (DisbursementStatus $s) => $s->value, $allowed));
            throw new DomainException(
                "Aksi tidak valid untuk status \"{$disbursement->status->value}\". Status yang diizinkan: {$allowedLabels}."
            );
        }
    }
}
