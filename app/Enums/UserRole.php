<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case BENDAHARA = 'bendahara';
    case VERIFIKATOR = 'verifikator';
    case KETUA = 'ketua';
    case AUDITOR = 'auditor';
    case DONATUR = 'donatur';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::BENDAHARA => 'Bendahara',
            self::VERIFIKATOR => 'Verifikator',
            self::KETUA => 'Ketua',
            self::AUDITOR => 'Auditor',
            self::DONATUR => 'Donatur',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
