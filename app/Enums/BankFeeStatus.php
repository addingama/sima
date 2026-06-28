<?php

namespace App\Enums;

enum BankFeeStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case REVERSED = 'reversed';
}
