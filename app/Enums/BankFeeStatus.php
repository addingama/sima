<?php

namespace App\Enums;

enum BankFeeStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case DEFERRED = 'deferred';
    case REVERSED = 'reversed';
}
