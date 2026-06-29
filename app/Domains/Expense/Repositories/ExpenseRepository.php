<?php

namespace App\Domains\Expense\Repositories;

use App\Enums\DisbursementStatus;
use App\Models\Disbursement;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExpenseRepository
{
    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            Disbursement::query()->with(['account:id,code,name', 'program:id,code,name', 'fundSources.fund:id,code,name']),
            $query,
            searchColumns: ['disbursement_number', 'payee', 'description'],
            sortable: ['disbursement_date', 'disbursement_number', 'amount', 'created_at'],
            defaultSort: 'disbursement_date',
            filterCallbacks: [
                'fund_id' => fn ($q, $v) => $q->whereHas('fundSources', fn ($s) => $s->where('fund_id', $v)),
                'from' => fn ($q, $v) => $q->whereDate('disbursement_date', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('disbursement_date', '<=', $v),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
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
