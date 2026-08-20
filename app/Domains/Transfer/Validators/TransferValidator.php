<?php

namespace App\Domains\Transfer\Validators;

use App\Enums\AccountTransferStatus;
use App\Exceptions\DomainException;
use App\Models\Account;
use App\Models\AccountTransfer;

class TransferValidator
{
    public function assertDraft(AccountTransfer $transfer): void
    {
        if ($transfer->status !== AccountTransferStatus::DRAFT) {
            throw new DomainException('Hanya transfer berstatus draft yang dapat diposting.');
        }
    }

    public function assertPostedForReversal(AccountTransfer $transfer): void
    {
        if ($transfer->status !== AccountTransferStatus::POSTED) {
            throw new DomainException('Hanya transfer berstatus posted yang dapat di-reverse.');
        }
    }

    public function assertAccountsValid(int $fromAccountId, int $toAccountId): void
    {
        if ($fromAccountId === $toAccountId) {
            throw new DomainException('Rekening sumber dan tujuan harus berbeda.');
        }

        $from = Account::query()->find($fromAccountId);
        $to = Account::query()->find($toAccountId);

        if ($from === null || $to === null) {
            throw new DomainException('Rekening sumber atau tujuan tidak ditemukan.');
        }

        if (! $from->is_active || ! $to->is_active) {
            throw new DomainException('Rekening sumber dan tujuan harus aktif.');
        }
    }
}
