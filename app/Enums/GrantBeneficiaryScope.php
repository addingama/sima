<?php

namespace App\Enums;

enum GrantBeneficiaryScope: string
{
    case INDIVIDUAL = 'individual';
    case COLLECTIVE = 'collective';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::COLLECTIVE => 'Kolektif',
        };
    }
}
