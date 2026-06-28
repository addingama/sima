<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case SUBMITTED = 'submitted';
    case VERIFIED = 'verified';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case POSTED = 'posted';
    case REVERSED = 'reversed';
}
