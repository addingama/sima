<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REVERSED = 'reversed';

    /** Status yang berarti ledger sudah diposting. */
    public function isPosted(): bool
    {
        return $this === self::APPROVED;
    }
}
