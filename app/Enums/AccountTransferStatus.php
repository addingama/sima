<?php

namespace App\Enums;

enum AccountTransferStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case REVERSED = 'reversed';
}
