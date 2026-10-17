<?php

namespace App\Enums;

enum PettyCashTopupRequestStatus: string
{
    case PENDING = 'pending';
    case FUNDED = 'funded';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
