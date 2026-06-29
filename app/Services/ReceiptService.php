<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\ApprovalAction;
use App\Enums\LedgerMovement;
use App\Enums\ReceiptStatus;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Penerimaan (uang masuk).
 *
 * Aturan:
 *  - Alokasi Dana Amanah disimpan bersama penerimaan.
 *  - Total alokasi WAJIB sama dengan total penerimaan (tidak ada sisa).
 *  - draft -> submitted -> approved -> [reversed] / rejected.
 *  - APPROVE memposting ledger per alokasi: debit kas/bank (+), credit tiap Dana Amanah (+).
 */
class ReceiptService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{fund_id:int, amount:string|float, program_id?:int|null, note?:string|null}>  $allocations
     */
    public function create(array $data, array $allocations, User $actor): Receipt
    {
        $this->assertAllocationsMatch((string) $data['amount'], $allocations);

        return DB::transaction(function () use ($data, $allocations, $actor): Receipt {
            $receipt = Receipt::create([
                ...$data,
                'amount' => bcadd((string) $data['amount'], '0', 2),
                'receipt_number' => $data['receipt_number'] ?? $this->numbers->next('RCP'),
                'status' => ReceiptStatus::DRAFT->value,
                'created_by' => $actor->getKey(),
            ]);

            $this->syncAllocations($receipt, $allocations, $actor);
            $this->audit->log($receipt, 'created', null, $receipt->toArray(), $actor);

            return $receipt->load('allocations');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $allocations
     */
    public function update(Receipt $receipt, array $data, ?array $allocations, User $actor): Receipt
    {
        $this->assertStatus($receipt, [ReceiptStatus::DRAFT]);

        return DB::transaction(function () use ($receipt, $data, $allocations, $actor): Receipt {
            $before = $receipt->toArray();
            $receipt->update($data);

            if ($allocations !== null) {
                $this->assertAllocationsMatch((string) $receipt->amount, $allocations);
                $receipt->allocations()->delete();
                $this->syncAllocations($receipt, $allocations, $actor);
            }

            $this->audit->log($receipt, 'updated', $before, $receipt->fresh()->toArray(), $actor);

            return $receipt->refresh()->load('allocations');
        });
    }

    public function submit(Receipt $receipt, User $actor): Receipt
    {
        $this->assertStatus($receipt, [ReceiptStatus::DRAFT]);

        if ($receipt->allocations()->count() === 0) {
            throw new DomainException('Penerimaan harus memiliki minimal satu alokasi Dana Amanah.');
        }
        $this->assertAllocationsMatchExisting($receipt);

        return DB::transaction(function () use ($receipt, $actor): Receipt {
            $receipt->update([
                'status' => ReceiptStatus::SUBMITTED->value,
                'submitted_at' => now(),
                'submitted_by' => $actor->getKey(),
            ]);
            $this->approvals->record($receipt, ApprovalAction::SUBMITTED, $actor);

            return $receipt->refresh();
        });
    }

    /** Persetujuan final — memposting ledger per alokasi. */
    public function approve(Receipt $receipt, User $actor, ?string $notes = null): Receipt
    {
        $this->assertStatus($receipt, [ReceiptStatus::SUBMITTED]);
        $this->assertAllocationsMatchExisting($receipt);

        return DB::transaction(function () use ($receipt, $actor, $notes): Receipt {
            $fundLines = $receipt->allocations->map(fn ($alloc) => [
                'fund_id' => $alloc->fund_id,
                'amount' => bcadd((string) $alloc->amount, '0', 2),
            ])->all();

            $this->ledger->postAmanahMovement(
                TransactionType::RECEIPT,
                $receipt->id,
                $receipt->account_id,
                $fundLines,
                LedgerMovement::IN,
                'Penerimaan '.$receipt->receipt_number,
            );

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

            $this->approvals->record($receipt, ApprovalAction::APPROVED, $actor, $notes);
            $this->approvals->record($receipt, ApprovalAction::POSTED, $actor);

            return $receipt->refresh();
        });
    }

    public function reject(Receipt $receipt, User $actor, string $reason): Receipt
    {
        $this->assertStatus($receipt, [ReceiptStatus::SUBMITTED]);

        return DB::transaction(function () use ($receipt, $actor, $reason): Receipt {
            $receipt->update([
                'status' => ReceiptStatus::REJECTED->value,
                'rejected_at' => now(),
                'rejected_by' => $actor->getKey(),
                'rejection_reason' => $reason,
            ]);
            $this->approvals->record($receipt, ApprovalAction::REJECTED, $actor, $reason);

            return $receipt->refresh();
        });
    }

    /** @param array<int, array<string, mixed>> $allocations */
    private function syncAllocations(Receipt $receipt, array $allocations, User $actor): void
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

    /** @param array<int, array<string, mixed>> $allocations */
    private function assertAllocationsMatch(string $amount, array $allocations): void
    {
        if (count($allocations) === 0) {
            throw new DomainException('Penerimaan harus memiliki minimal satu alokasi Dana Amanah.');
        }

        $total = '0.00';
        foreach ($allocations as $a) {
            if (bccomp((string) $a['amount'], '0', 2) <= 0) {
                throw new DomainException('Nominal tiap alokasi harus lebih besar dari nol.');
            }
            $total = bcadd($total, (string) $a['amount'], 2);
        }

        if (bccomp($total, bcadd($amount, '0', 2), 2) !== 0) {
            throw new DomainException(
                "Total alokasi ({$total}) harus sama dengan total penerimaan ({$amount})."
            );
        }
    }

    private function assertAllocationsMatchExisting(Receipt $receipt): void
    {
        $total = (string) $receipt->allocations()->sum('amount');
        if (bccomp(bcadd($total, '0', 2), bcadd((string) $receipt->amount, '0', 2), 2) !== 0) {
            throw new DomainException(
                "Total alokasi ({$total}) harus sama dengan total penerimaan ({$receipt->amount})."
            );
        }
    }

    /** @param array<int, ReceiptStatus> $allowed */
    private function assertStatus(Receipt $receipt, array $allowed): void
    {
        if (! in_array($receipt->status, $allowed, true)) {
            $allowedLabels = implode(', ', array_map(fn (ReceiptStatus $s) => $s->value, $allowed));
            throw new DomainException(
                "Aksi tidak valid untuk status \"{$receipt->status->value}\". Status diizinkan: {$allowedLabels}."
            );
        }
    }
}
