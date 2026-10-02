<?php

namespace App\Enums;

enum GrantBeneficiaryType: string
{
    case INDIVIDUAL = 'individual';
    case ORGANIZATION = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Perorangan',
            self::ORGANIZATION => 'Organisasi / Instansi',
        };
    }
}
