<?php

namespace App\Enums;

enum AllocationStatus: string
{
    case POSTED = 'posted';
    case REVERSED = 'reversed';
}
