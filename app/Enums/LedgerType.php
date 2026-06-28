<?php

namespace App\Enums;

enum LedgerType: string
{
    case OPENING = 'opening';
    case RECEIPT = 'receipt';
    case ALLOCATION_OUT = 'allocation_out';
    case ALLOCATION_IN = 'allocation_in';
    case DISBURSEMENT = 'disbursement';
    case BANK_FEE = 'bank_fee';
    case TRANSFER = 'transfer';
    case REVERSAL = 'reversal';
}
