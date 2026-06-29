<?php

namespace App\Domains\Reconciliation\Repositories;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BankReconciliationRepository
{
    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            BankReconciliation::query()->with('account:id,code,name'),
            $query,
            searchColumns: ['notes'],
            sortable: ['period_end', 'period_start', 'created_at', 'id'],
            defaultSort: 'period_end',
            filterCallbacks: [
                'from' => fn ($q, $v) => $q->whereDate('period_end', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('period_end', '<=', $v),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): BankReconciliation
    {
        return BankReconciliation::create($data);
    }

    /** @param array<string, mixed> $data */
    public function addLine(BankReconciliation $reconciliation, array $data): BankReconciliationLine
    {
        return $reconciliation->lines()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function complete(BankReconciliation $reconciliation, array $data): BankReconciliation
    {
        $reconciliation->update($data);

        return $reconciliation;
    }
}
