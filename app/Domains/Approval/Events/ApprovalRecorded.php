<?php

namespace App\Domains\Approval\Events;

use App\Domains\Approval\DTOs\RecordApprovalDto;
use App\Models\Approval;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly Approval $approval,
        public readonly RecordApprovalDto $dto,
    ) {}
}
