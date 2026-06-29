<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\DTOs\AuditLogDto;
use App\Domains\Audit\Events\AuditLogged;
use App\Domains\Audit\Repositories\AuditLogRepository;
use App\Domains\Audit\Validators\AuditLogValidator;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Audit;

class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $repository,
        private readonly AuditLogValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        Model $entity,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
        ?string $tags = null,
    ): Audit {
        $this->validator->assertAction($action);

        $actor ??= (Auth::user() instanceof User ? Auth::user() : null);

        $dto = new AuditLogDto($entity, $action, $before, $after, $actor, $tags);
        $audit = $this->repository->create($dto);

        event(new AuditLogged($audit, $dto));

        return $audit;
    }
}
