<?php

namespace App\Domains\Expense\Validators;

use App\Enums\BankFeeStatus;
use App\Exceptions\DomainException;
use App\Models\BankFee;
use App\Models\Fund;

class BankFeeValidator
{
    public function assertDraft(BankFee $fee): void
    {
        if ($fee->status !== BankFeeStatus::DRAFT) {
            throw new DomainException('Hanya biaya bank berstatus draft yang dapat diposting.');
        }
    }

    public function assertPostedForReversal(BankFee $fee): void
    {
        if ($fee->status !== BankFeeStatus::POSTED) {
            throw new DomainException('Hanya biaya bank berstatus posted yang dapat dibatalkan (reversal).');
        }
    }

    public function assertFundAllowed(int $fundId): void
    {
        $fund = Fund::findOrFail($fundId);

        if ($fund->type === 'restricted') {
            throw new DomainException(
                "Biaya bank tidak boleh dibebankan ke Dana Amanah khusus \"{$fund->name}\" (restricted). Gunakan Dana Operasional."
            );
        }
    }
}
