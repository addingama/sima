<?php

namespace App\Enums;

enum GrantApplicationStatus: string
{
    case DRAFT = 'draft';
    case VERIFICATION = 'verification';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rekomendasi',
            self::VERIFICATION => 'Verifikasi',
            self::PENDING_APPROVAL => 'Menunggu approval',
            self::APPROVED => 'Siap diserahkan',
            self::COMPLETED => 'Selesai',
            self::REJECTED => 'Ditolak',
        };
    }
}
