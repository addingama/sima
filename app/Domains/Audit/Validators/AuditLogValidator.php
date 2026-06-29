<?php

namespace App\Domains\Audit\Validators;

use App\Exceptions\DomainException;

class AuditLogValidator
{
    public function assertAction(string $action): void
    {
        if ($action === '') {
            throw new DomainException('Aksi audit wajib diisi.');
        }
    }
}
