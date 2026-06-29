<?php

namespace App\Domains\Expense\Repositories;

use App\Enums\DisbursementStatus;
use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExpenseRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Disbursement::query()
            ->with(['account:id,code,name', 'program:id,code,name', 'fundSources.fund:id,code,name'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['fund_id']), fn ($q) => $q->whereHas('fundSources', fn ($s) => $s->where('fund_id', $filters['fund_id'])))
            ->when(isset($filters['program_id']), fn ($q) => $q->where('program_id', $filters['program_id']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('disbursement_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('disbursement_date', '<=', $filters['to']))
            ->orderByDesc('disbursement_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Disbursement
    {
        return Disbursement::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Disbursement $expense, array $data): Disbursement
    {
        $expense->update($data);

        return $expense;
    }

    /** @param array<int, array<string, mixed>> $sources */
    public function syncSources(Disbursement $expense, array $sources): void
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

    public function markSubmitted(Disbursement $expense, User $actor): Disbursement
    {
        $expense->update([
            'status' => DisbursementStatus::SUBMITTED->value,
            'submitted_at' => now(),
            'submitted_by' => $actor->getKey(),
        ]);

        return $expense;
    }

    public function markVerified(Disbursement $expense, User $actor): Disbursement
    {
        $expense->update([
            'status' => DisbursementStatus::VERIFIED->value,
            'verified_at' => now(),
            'verified_by' => $actor->getKey(),
        ]);

        return $expense;
    }

    public function markApproved(Disbursement $expense, User $actor): Disbursement
    {
        $expense->update([
            'status' => DisbursementStatus::APPROVED->value,
            'approved_at' => now(),
            'approved_by' => $actor->getKey(),
            'posted_at' => now(),
        ]);

        return $expense;
    }

    public function markRejected(Disbursement $expense, User $actor, string $reason): Disbursement
    {
        $expense->update([
            'status' => DisbursementStatus::REJECTED->value,
            'rejected_at' => now(),
            'rejected_by' => $actor->getKey(),
            'rejection_reason' => $reason,
        ]);

        return $expense;
    }

    public function markReversed(Disbursement $expense, User $actor, string $reason): Disbursement
    {
        $expense->update([
            'status' => DisbursementStatus::REVERSED->value,
            'reversed_at' => now(),
            'reversed_by' => $actor->getKey(),
            'reversal_reason' => $reason,
        ]);

        return $expense;
    }

    public function deleteSources(Disbursement $expense): void
    {
        $expense->fundSources()->delete();
    }
}
