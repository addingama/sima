<?php

namespace App\Enums;

enum DisbursementStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case VERIFIED = 'verified';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REVERSED = 'reversed';

    /** Status yang berarti dana sudah benar-benar keluar (ter-post ke ledger). */
    public function isPosted(): bool
    {
        return $this === self::APPROVED;
    }
}
