<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\DisbursementStatus;
use App\Enums\LedgerMovement;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pengeluaran (Expense / Disbursement).
 *
 * Aturan:
 *  - Sumber Dana Amanah disimpan di expense_fund_sources (boleh > 1).
 *  - Total sumber dana WAJIB sama dengan total pengeluaran.
 *  - Saldo TIAP Dana Amanah harus cukup (divalidasi saat submit/verify/approve).
 *  - draft -> submitted -> verified -> approved (post ledger) -> [reversed] / rejected.
 *  - APPROVE memposting ledger per sumber: credit kas/bank (-), debit tiap Dana Amanah (-).
 */
class ExpenseService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
        private readonly TrustFundBalanceService $balances,
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{fund_id:int, amount:string|float, program_id?:int|null, note?:string|null}>  $sources
     */
    public function create(array $data, array $sources, User $actor): Disbursement
    {
        $amount = bcadd((string) $data['amount'], '0', 2);
        $this->assertSourcesMatch($amount, $sources);

        return DB::transaction(function () use ($data, $sources, $amount, $actor): Disbursement {
            $expense = Disbursement::create([
                ...$data,
                'amount' => $amount,
                'disbursement_number' => $data['disbursement_number'] ?? $this->numbers->next('DSB'),
                'status' => DisbursementStatus::DRAFT->value,
                'created_by' => $actor->getKey(),
            ]);

            $this->syncSources($expense, $sources);
            $this->audit->log($expense, 'created', null, $expense->toArray(), $actor);

            return $expense->load('fundSources');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $sources
     */
    public function update(Disbursement $expense, array $data, ?array $sources, User $actor): Disbursement
    {
        $this->assertStatus($expense, [DisbursementStatus::DRAFT]);

        return DB::transaction(function () use ($expense, $data, $sources, $actor): Disbursement {
            $before = $expense->toArray();
            $expense->update($data);

            if ($sources !== null) {
                $this->assertSourcesMatch((string) $expense->amount, $sources);
                $expense->fundSources()->delete();
                $this->syncSources($expense, $sources);
            }

            $this->audit->log($expense, 'updated', $before, $expense->fresh()->toArray(), $actor);

            return $expense->refresh()->load('fundSources');
        });
    }

    public function submit(Disbursement $expense, User $actor): Disbursement
    {
        $this->assertStatus($expense, [DisbursementStatus::DRAFT]);
        $this->assertFundsAvailable($expense);

        return DB::transaction(function () use ($expense, $actor): Disbursement {
            $expense->update([
                'status' => DisbursementStatus::SUBMITTED->value,
                'submitted_at' => now(),
                'submitted_by' => $actor->getKey(),
            ]);
            $this->approvals->record($expense, ApprovalAction::SUBMITTED, $actor);

            return $expense->refresh();
        });
    }

    public function verify(Disbursement $expense, User $actor, ?string $notes = null): Disbursement
    {
        $this->assertStatus($expense, [DisbursementStatus::SUBMITTED]);
        $this->assertFundsAvailable($expense);

        return DB::transaction(function () use ($expense, $actor, $notes): Disbursement {
            $expense->update([
                'status' => DisbursementStatus::VERIFIED->value,
                'verified_at' => now(),
                'verified_by' => $actor->getKey(),
            ]);
            $this->approvals->record($expense, ApprovalAction::VERIFIED, $actor, $notes);

            return $expense->refresh();
        });
    }

    /** Persetujuan final (Ketua) — memposting ledger (satu leg per sumber dana). */
    public function approve(Disbursement $expense, User $actor, ?string $notes = null): Disbursement
    {
        $this->assertStatus($expense, [DisbursementStatus::VERIFIED]);
        $this->assertFundsAvailable($expense);

        return DB::transaction(function () use ($expense, $actor, $notes): Disbursement {
            $fundLines = $expense->fundSources->map(fn ($source) => [
                'fund_id' => $source->fund_id,
                'amount' => bcadd((string) $source->amount, '0', 2),
            ])->all();

            $this->ledger->postAmanahMovement(
                TransactionType::EXPENSE,
                $expense->id,
                $expense->account_id,
                $fundLines,
                LedgerMovement::OUT,
                'Pengeluaran '.$expense->disbursement_number,
            );

            $expense->update([
                'status' => DisbursementStatus::APPROVED->value,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
                'posted_at' => now(),
            ]);
            $this->approvals->record($expense, ApprovalAction::APPROVED, $actor, $notes);
            $this->approvals->record($expense, ApprovalAction::POSTED, $actor);

            return $expense->refresh();
        });
    }

    public function reject(Disbursement $expense, User $actor, string $reason): Disbursement
    {
        $this->assertStatus($expense, [DisbursementStatus::SUBMITTED, DisbursementStatus::VERIFIED]);

        return DB::transaction(function () use ($expense, $actor, $reason): Disbursement {
            $expense->update([
                'status' => DisbursementStatus::REJECTED->value,
                'rejected_at' => now(),
                'rejected_by' => $actor->getKey(),
                'rejection_reason' => $reason,
            ]);
            $this->approvals->record($expense, ApprovalAction::REJECTED, $actor, $reason);

            return $expense->refresh();
        });
    }

    /** @param array<int, array<string, mixed>> $sources */
    private function syncSources(Disbursement $expense, array $sources): void
    {
        foreach ($sources as $s) {
            $expense->fundSources()->create([
                'fund_id' => $s['fund_id'],
                'program_id' => $s['program_id'] ?? null,
                'amount' => bcadd((string) $s['amount'], '0', 2),
                'note' => $s['note'] ?? null,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $sources */
    private function assertSourcesMatch(string $amount, array $sources): void
    {
        if (count($sources) === 0) {
            throw new DomainException('Pengeluaran harus memiliki minimal satu sumber Dana Amanah.');
        }

        $total = '0.00';
        foreach ($sources as $s) {
            if (bccomp((string) $s['amount'], '0', 2) <= 0) {
                throw new DomainException('Nominal tiap sumber dana harus lebih besar dari nol.');
            }
            $total = bcadd($total, (string) $s['amount'], 2);
        }

        if (bccomp($total, $amount, 2) !== 0) {
            throw new DomainException(
                "Total sumber dana ({$total}) harus sama dengan total pengeluaran ({$amount})."
            );
        }
    }

    /** Validasi saldo TIAP Dana Amanah sumber & saldo akun mencukupi. */
    private function assertFundsAvailable(Disbursement $expense): void
    {
        $expense->loadMissing('fundSources');

        $perFund = [];
        foreach ($expense->fundSources as $source) {
            $perFund[$source->fund_id] = bcadd($perFund[$source->fund_id] ?? '0.00', (string) $source->amount, 2);
        }

        foreach ($perFund as $fundId => $needed) {
            $this->balances->assertFundSufficient((int) $fundId, $needed);
        }

        $this->balances->assertAccountSufficient($expense->account_id, bcadd((string) $expense->amount, '0', 2));
    }

    /** @param array<int, DisbursementStatus> $allowed */
    private function assertStatus(Disbursement $expense, array $allowed): void
    {
        if (! in_array($expense->status, $allowed, true)) {
            $allowedLabels = implode(', ', array_map(fn (DisbursementStatus $s) => $s->value, $allowed));
            throw new DomainException(
                "Aksi tidak valid untuk status \"{$expense->status->value}\". Status diizinkan: {$allowedLabels}."
            );
        }
    }
}
