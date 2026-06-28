<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\LedgerType;
use App\Enums\ReceiptStatus;
use App\Exceptions\DomainException;
use App\Models\Fund;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AllocationService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Membuat sekaligus memposting alokasi: memindahkan dana dari suspense ke Dana Amanah tujuan.
     *
     * @param array<string, mixed> $data [fund_id, amount, program_id?, note?]
     */
    public function allocate(Receipt $receipt, array $data, User $actor): ReceiptAllocation
    {
        if ($receipt->status !== ReceiptStatus::POSTED) {
            throw new DomainException('Alokasi hanya dapat dibuat untuk penerimaan yang sudah diposting.');
        }

        $amount = bcadd((string) $data['amount'], '0', 2);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new DomainException('Nominal alokasi harus lebih besar dari nol.');
        }

        $unallocated = $receipt->unallocatedAmount();
        if (bccomp($amount, $unallocated, 2) > 0) {
            throw new DomainException(
                "Nominal alokasi ({$amount}) melebihi sisa yang belum dialokasikan ({$unallocated})."
            );
        }

        return DB::transaction(function () use ($receipt, $data, $amount, $actor): ReceiptAllocation {
            $suspense = Fund::findBySystemKey(Fund::KEY_SUSPENSE);

            $allocation = $receipt->allocations()->create([
                'fund_id' => $data['fund_id'],
                'program_id' => $data['program_id'] ?? null,
                'amount' => $amount,
                'note' => $data['note'] ?? null,
                'status' => AllocationStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor->getKey(),
                'created_by' => $actor->getKey(),
            ]);

            $this->ledger->post([
                [
                    'entry_date' => now()->toDateString(),
                    'account_id' => $receipt->account_id,
                    'fund_id' => $suspense->id,
                    'amount' => bcmul($amount, '-1', 2),
                    'type' => LedgerType::ALLOCATION_OUT,
                    'source' => $allocation,
                    'memo' => 'Alokasi keluar dari suspense',
                ],
                [
                    'entry_date' => now()->toDateString(),
                    'account_id' => $receipt->account_id,
                    'fund_id' => $data['fund_id'],
                    'program_id' => $data['program_id'] ?? null,
                    'amount' => $amount,
                    'type' => LedgerType::ALLOCATION_IN,
                    'source' => $allocation,
                    'memo' => 'Alokasi masuk ke dana tujuan',
                ],
            ], $actor);

            return $allocation->refresh();
        });
    }

    /** Reversal alokasi: mengembalikan dana dari Dana Amanah tujuan kembali ke suspense. */
    public function reverse(ReceiptAllocation $allocation, User $actor, string $reason): ReceiptAllocation
    {
        if ($allocation->status !== AllocationStatus::POSTED) {
            throw new DomainException('Hanya alokasi berstatus posted yang dapat dibatalkan.');
        }

        return DB::transaction(function () use ($allocation, $actor, $reason): ReceiptAllocation {
            $this->ledger->reverse($allocation, $actor, 'Reversal alokasi: '.$reason);

            $allocation->update([
                'status' => AllocationStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);

            return $allocation->refresh();
        });
    }
}
