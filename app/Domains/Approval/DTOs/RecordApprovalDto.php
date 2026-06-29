<?php

namespace App\Domains\Approval\DTOs;

use App\Enums\ApprovalAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

readonly class RecordApprovalDto
{
    public function __construct(
        public Model $entity,
        public ApprovalAction $action,
        public ?User $actor = null,
        public ?string $notes = null,
    ) {}
}
