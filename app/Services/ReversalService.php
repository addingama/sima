<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\ApprovalAction;
use App\Enums\BankFeeStatus;
use App\Enums\DisbursementStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\BankFee;
use App\Models\Disbursement;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Void/Reversal terpusat untuk transaksi finansial.
 *
 * Prinsip:
 *  - Transaksi approved TIDAK boleh diedit; koreksi hanya via reversal.
 *  - Reversal membuat entri ledger negasi (membalik ledger lama), bukan menghapus.
 *  - Wajib menyimpan alasan & pelaku reversal.
 *  - Invariant saldo tetap dijaga (mis. penerimaan tak bisa dibalik bila dananya sudah terpakai).
 */
class ReversalService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
    ) {}

    public function reverseReceipt(Receipt $receipt, User $actor, string $reason): Receipt
    {
        if ($receipt->status !== ReceiptStatus::APPROVED) {
            throw new DomainException('Hanya penerimaan yang sudah approved yang dapat dibatalkan (reversal).');
        }

        return DB::transaction(function () use ($receipt, $actor, $reason): Receipt {
            $this->ledger->reverse(
                TransactionType::RECEIPT,
                $receipt->id,
                $receipt->id,
                'Reversal penerimaan: '.$reason,
            );

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

            $this->finishLog($receipt, $actor, $reason);

            return $receipt->refresh();
        });
    }

    public function reverseExpense(Disbursement $expense, User $actor, string $reason): Disbursement
    {
        if ($expense->status !== DisbursementStatus::APPROVED) {
            throw new DomainException('Hanya pengeluaran yang sudah approved yang dapat dibatalkan (reversal).');
        }

        return DB::transaction(function () use ($expense, $actor, $reason): Disbursement {
            $this->ledger->reverse(
                TransactionType::EXPENSE,
                $expense->id,
                $expense->id,
                'Reversal pengeluaran: '.$reason,
            );

            $expense->update([
                'status' => DisbursementStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);

            $this->finishLog($expense, $actor, $reason);

            return $expense->refresh();
        });
    }

    public function reverseBankFee(BankFee $fee, User $actor, string $reason): BankFee
    {
        if ($fee->status !== BankFeeStatus::POSTED) {
            throw new DomainException('Hanya biaya bank berstatus posted yang dapat dibatalkan (reversal).');
        }

        return DB::transaction(function () use ($fee, $actor, $reason): BankFee {
            $this->ledger->reverse(
                TransactionType::BANK_FEE,
                $fee->id,
                $fee->id,
                'Reversal biaya bank: '.$reason,
            );

            $fee->update([
                'status' => BankFeeStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);

            $this->audit->log($fee, ApprovalAction::REVERSED->value, null, ['reason' => $reason], $actor, 'reversal');

            return $fee->refresh();
        });
    }

    private function finishLog(Model $entity, User $actor, string $reason): void
    {
        // Catat di approval history (entitas memakai trait HasApprovals).
        if (method_exists($entity, 'approvals')) {
            $this->approvals->record($entity, ApprovalAction::REVERSED, $actor, $reason);
        } else {
            $this->audit->log($entity, ApprovalAction::REVERSED->value, null, ['reason' => $reason], $actor, 'reversal');
        }
    }
}
