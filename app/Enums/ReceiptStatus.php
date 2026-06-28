<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case REVERSED = 'reversed';
}
