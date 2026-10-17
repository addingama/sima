<?php

namespace App\Enums;

enum PettyCashFloatStatus: string
{
    case DRAFT = 'draft';
    case REPORTING = 'reporting';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}
