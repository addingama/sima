<?php

namespace App\Domains\Receipt\Validators;

use App\Enums\ReceiptStatus;
use App\Exceptions\DomainException;
use App\Models\Receipt;

class ReceiptValidator
{
    /** @param array<int, array<string, mixed>> $allocations */
    public function assertAllocationsMatch(string $amount, array $allocations): void
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

    public function assertAllocationsMatchExisting(Receipt $receipt): void
    {
        $total = (string) $receipt->allocations()->sum('amount');
        if (bccomp(bcadd($total, '0', 2), bcadd((string) $receipt->amount, '0', 2), 2) !== 0) {
            throw new DomainException(
                "Total alokasi ({$total}) harus sama dengan total penerimaan ({$receipt->amount})."
            );
        }
    }

    public function assertHasAllocations(Receipt $receipt): void
    {
        if ($receipt->allocations()->count() === 0) {
            throw new DomainException('Penerimaan harus memiliki minimal satu alokasi Dana Amanah.');
        }
    }

    /** @param array<int, ReceiptStatus> $allowed */
    public function assertStatus(Receipt $receipt, array $allowed): void
    {
        if (! in_array($receipt->status, $allowed, true)) {
            $allowedLabels = implode(', ', array_map(fn (ReceiptStatus $s) => $s->value, $allowed));
            throw new DomainException(
                "Aksi tidak valid untuk status \"{$receipt->status->value}\". Status diizinkan: {$allowedLabels}."
            );
        }
    }

    public function assertApprovedForReversal(Receipt $receipt): void
    {
        if ($receipt->status !== ReceiptStatus::APPROVED) {
            throw new DomainException('Hanya penerimaan yang sudah approved yang dapat dibatalkan (reversal).');
        }
    }
}
