<?php

namespace App\Domains\Audit\Repositories;

use App\Domains\Audit\DTOs\AuditLogDto;
use OwenIt\Auditing\Contracts\Audit;

class AuditLogRepository
{
    public function create(AuditLogDto $dto): Audit
    {
        /** @var class-string<Audit&\Illuminate\Database\Eloquent\Model> $auditModel */
        $auditModel = config('audit.implementation', \OwenIt\Auditing\Models\Audit::class);

        $request = request();
        $actor = $dto->actor;

        return $auditModel::create([
            'user_type' => $actor ? $actor->getMorphClass() : null,
            'user_id' => $actor?->getKey(),
            'event' => $dto->action,
            'auditable_type' => $dto->entity->getMorphClass(),
            'auditable_id' => $dto->entity->getKey(),
            'old_values' => $dto->before ?? [],
            'new_values' => $dto->after ?? [],
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'tags' => $dto->tags ?? 'action',
        ]);
    }

    public function paginate(array $filters, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        /** @var class-string<Audit&\Illuminate\Database\Eloquent\Model> $auditModel */
        $auditModel = config('audit.implementation', \OwenIt\Auditing\Models\Audit::class);

        return $auditModel::query()
            ->with('user:id,name,email')
            ->when(isset($filters['auditable_type']), fn ($q) => $q->where('auditable_type', $filters['auditable_type']))
            ->when(isset($filters['auditable_id']), fn ($q) => $q->where('auditable_id', $filters['auditable_id']))
            ->when(isset($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(isset($filters['event']), fn ($q) => $q->where('event', $filters['event']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
