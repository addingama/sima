<?php

namespace App\Domains\Reconciliation\Validators;

use App\Exceptions\DomainException;
use App\Models\BankReconciliation;

class ReconciliationValidator
{
    public function assertDraft(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->status !== 'draft') {
            throw new DomainException('Hanya rekonsiliasi berstatus draft yang dapat diubah.');
        }
    }
}
