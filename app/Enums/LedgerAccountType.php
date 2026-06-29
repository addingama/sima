<?php

namespace App\Enums;

/** Dimensi akun buku besar Amanah. */
enum LedgerAccountType: string
{
    case ACCOUNT = 'account';
    case FUND = 'fund';
}
