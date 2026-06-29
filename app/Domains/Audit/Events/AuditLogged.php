<?php

namespace App\Domains\Audit\Events;

use App\Domains\Audit\DTOs\AuditLogDto;
use Illuminate\Foundation\Events\Dispatchable;
use OwenIt\Auditing\Contracts\Audit;

class AuditLogged
{
    use Dispatchable;

    public function __construct(
        public readonly Audit $audit,
        public readonly AuditLogDto $dto,
    ) {}
}
