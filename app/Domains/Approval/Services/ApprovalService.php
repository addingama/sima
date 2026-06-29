<?php

namespace App\Domains\Approval\Services;

use App\Domains\Approval\DTOs\RecordApprovalDto;
use App\Domains\Approval\Events\ApprovalRecorded;
use App\Domains\Approval\Repositories\ApprovalRepository;
use App\Domains\Approval\Validators\ApprovalValidator;
use App\Enums\ApprovalAction;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalService
{
    public function __construct(
        private readonly ApprovalRepository $repository,
        private readonly ApprovalValidator $validator,
    ) {}

    public function record(Model $entity, ApprovalAction $action, ?User $actor, ?string $notes = null): Approval
    {
        $this->validator->assertApprovable($entity);

        $dto = new RecordApprovalDto($entity, $action, $actor, $notes);
        $approval = $this->repository->create($dto);

        event(new ApprovalRecorded($approval, $dto));

        return $approval;
    }
}
