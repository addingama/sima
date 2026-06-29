<?php

namespace App\Domains\Reconciliation\Repositories;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BankReconciliationRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return BankReconciliation::query()
            ->with('account:id,code,name')
            ->when(isset($filters['account_id']), fn ($q) => $q->where('account_id', $filters['account_id']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('period_end')
            ->paginate($perPage);
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
