<?php

namespace App\Enums;

enum PettyCashFundingKind: string
{
    case INITIAL = 'initial';
    case TOPUP = 'topup';
}
