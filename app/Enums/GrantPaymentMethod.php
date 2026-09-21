<?php

namespace App\Enums;

enum GrantPaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai',
            self::TRANSFER => 'Transfer',
        };
    }
}
