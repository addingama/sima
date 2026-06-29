<?php

namespace App\Enums;

/** Arah arus kas/dana pada pasangan akun Amanah. */
enum LedgerMovement: string
{
    case IN = 'in';
    case OUT = 'out';
}
