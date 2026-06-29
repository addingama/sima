<?php

namespace App\Domains\Receipt\Repositories;

use App\Enums\AllocationStatus;
use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReceiptRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Receipt::query()
            ->with(['account:id,code,name', 'donor:id,code,name'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['account_id']), fn ($q) => $q->where('account_id', $filters['account_id']))
            ->when(isset($filters['donor_id']), fn ($q) => $q->where('donor_id', $filters['donor_id']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('receipt_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('receipt_date', '<=', $filters['to']))
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Receipt
    {
        return Receipt::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Receipt $receipt, array $data): Receipt
    {
        $receipt->update($data);

        return $receipt;
    }

    /** @param array<int, array<string, mixed>> $allocations */
    public function syncAllocations(Receipt $receipt, array $allocations, User $actor): void
    {
        foreach ($allocations as $a) {
            $receipt->allocations()->create([
                'fund_id' => $a['fund_id'],
                'program_id' => $a['program_id'] ?? null,
                'amount' => bcadd((string) $a['amount'], '0', 2),
                'note' => $a['note'] ?? null,
                'status' => AllocationStatus::DRAFT->value,
                'created_by' => $actor->getKey(),
            ]);
        }
    }

    public function markSubmitted(Receipt $receipt, User $actor): Receipt
    {
        $receipt->update([
            'status' => ReceiptStatus::SUBMITTED->value,
            'submitted_at' => now(),
            'submitted_by' => $actor->getKey(),
        ]);

        return $receipt;
    }

    public function markApproved(Receipt $receipt, User $actor): Receipt
    {
        $receipt->allocations()->update([
            'status' => AllocationStatus::POSTED->value,
            'posted_at' => now(),
            'posted_by' => $actor->getKey(),
        ]);

        $receipt->update([
            'status' => ReceiptStatus::APPROVED->value,
            'approved_at' => now(),
            'approved_by' => $actor->getKey(),
            'posted_at' => now(),
        ]);

        return $receipt;
    }

    public function markRejected(Receipt $receipt, User $actor, string $reason): Receipt
    {
        $receipt->update([
            'status' => ReceiptStatus::REJECTED->value,
            'rejected_at' => now(),
            'rejected_by' => $actor->getKey(),
            'rejection_reason' => $reason,
        ]);

        return $receipt;
    }

    public function markReversed(Receipt $receipt, User $actor, string $reason): Receipt
    {
        $receipt->update([
            'status' => ReceiptStatus::REVERSED->value,
            'reversed_at' => now(),
            'reversed_by' => $actor->getKey(),
            'reversal_reason' => $reason,
        ]);

        $receipt->allocations()->update([
            'status' => AllocationStatus::REVERSED->value,
            'reversed_at' => now(),
            'reversed_by' => $actor->getKey(),
            'reversal_reason' => $reason,
        ]);

        return $receipt;
    }

    public function deleteAllocations(Receipt $receipt): void
    {
        $receipt->allocations()->delete();
    }
}
