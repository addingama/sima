<?php

namespace App\Domains\Transfer\Validators;

use App\Enums\AccountTransferStatus;
use App\Exceptions\DomainException;
use App\Models\Fund;
use App\Models\FundTransfer;

class FundTransferValidator
{
    public function assertDraft(FundTransfer $transfer): void
    {
        if ($transfer->status !== AccountTransferStatus::DRAFT) {
            throw new DomainException('Hanya transfer Dana Amanah berstatus draft yang dapat diposting.');
        }
    }

    public function assertPostedForReversal(FundTransfer $transfer): void
    {
        if ($transfer->status !== AccountTransferStatus::POSTED) {
            throw new DomainException('Hanya transfer Dana Amanah berstatus posted yang dapat di-reverse.');
        }
    }

    public function assertFundsValid(int $fromFundId, int $toFundId): void
    {
        if ($fromFundId === $toFundId) {
            throw new DomainException('Dana Amanah sumber dan tujuan harus berbeda.');
        }

        $from = Fund::query()->find($fromFundId);
        $to = Fund::query()->find($toFundId);

        if ($from === null || $to === null) {
            throw new DomainException('Dana Amanah sumber atau tujuan tidak ditemukan.');
        }

        if (! $from->is_active || ! $to->is_active) {
            throw new DomainException('Dana Amanah sumber dan tujuan harus aktif.');
        }
    }
}
