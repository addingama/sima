<?php

namespace App\Enums;

enum GrantBeneficiaryCountMethod: string
{
    case EXACT = 'exact';
    case ESTIMATED = 'estimated';

    public function label(): string
    {
        return match ($this) {
            self::EXACT => 'Hitungan pasti',
            self::ESTIMATED => 'Estimasi',
        };
    }
}
