<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\ApprovalAction;
use App\Enums\LedgerType;
use App\Enums\ReceiptStatus;
use App\Exceptions\DomainException;
use App\Models\Fund;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Receipt
    {
        $data['receipt_number'] = $data['receipt_number'] ?? $this->numbers->next('RCP');
        $data['status'] = ReceiptStatus::DRAFT->value;
        $data['created_by'] = $actor->getKey();

        return Receipt::create($data);
    }

    /** Posting penerimaan: uang masuk ke akun, ditampung di dana suspense. */
    public function post(Receipt $receipt, User $actor): Receipt
    {
        if ($receipt->status !== ReceiptStatus::DRAFT) {
            throw new DomainException('Hanya penerimaan berstatus draft yang dapat diposting.');
        }

        return DB::transaction(function () use ($receipt, $actor): Receipt {
            $suspense = $this->suspenseFund();

            $this->ledger->post([[
                'entry_date' => $receipt->receipt_date->toDateString(),
                'account_id' => $receipt->account_id,
                'fund_id' => $suspense->id,
                'amount' => bcadd((string) $receipt->amount, '0', 2),
                'type' => LedgerType::RECEIPT,
                'source' => $receipt,
                'memo' => 'Penerimaan '.$receipt->receipt_number,
            ]], $actor);

            $receipt->update([
                'status' => ReceiptStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor->getKey(),
            ]);

            $receipt->recordApproval(ApprovalAction::POSTED, $actor, 'Penerimaan diposting');

            return $receipt->refresh();
        });
    }

    /** Reversal penerimaan. Wajib tidak ada alokasi aktif yang masih ter-post. */
    public function reverse(Receipt $receipt, User $actor, string $reason): Receipt
    {
        if ($receipt->status !== ReceiptStatus::POSTED) {
            throw new DomainException('Hanya penerimaan berstatus posted yang dapat dibatalkan.');
        }

        $activeAllocations = $receipt->allocations()
            ->where('status', AllocationStatus::POSTED->value)
            ->count();

        if ($activeAllocations > 0) {
            throw new DomainException('Batalkan alokasi penerimaan ini terlebih dahulu sebelum membatalkan penerimaan.');
        }

        return DB::transaction(function () use ($receipt, $actor, $reason): Receipt {
            $this->ledger->reverse($receipt, $actor, 'Reversal penerimaan: '.$reason);

            $receipt->update([
                'status' => ReceiptStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);

            $receipt->recordApproval(ApprovalAction::REVERSED, $actor, $reason);

            return $receipt->refresh();
        });
    }

    private function suspenseFund(): Fund
    {
        $fund = Fund::findBySystemKey(Fund::KEY_SUSPENSE);

        if ($fund === null) {
            throw new DomainException('Dana sistem "suspense" belum tersedia. Jalankan seeder terlebih dahulu.');
        }

        return $fund;
    }
}
