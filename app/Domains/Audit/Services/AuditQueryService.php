<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Repositories\AuditLogRepository;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use OwenIt\Auditing\Models\Audit;

class AuditQueryService
{
    public function __construct(private readonly AuditLogRepository $repository) {}

    public function paginate(ListQueryDto $query): LengthAwarePaginator|CursorPaginator
    {
        /** @var class-string<Audit&\Illuminate\Database\Eloquent\Model> $auditModel */
        $auditModel = config('audit.implementation', Audit::class);

        $builder = ListQueryApplier::apply(
            $auditModel::query()->with('user:id,name,email'),
            $query,
            searchColumns: ['event', 'url', 'ip_address'],
            sortable: ['id', 'created_at', 'event'],
            defaultSort: 'id',
            filterCallbacks: [
                'from' => fn ($q, $v) => $q->whereDate('created_at', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('created_at', '<=', $v),
                'auditable_type' => fn ($q, $v) => $q->where('auditable_type', $v),
                'auditable_id' => fn ($q, $v) => $q->where('auditable_id', $v),
                'user_id' => fn ($q, $v) => $q->where('user_id', $v),
                'event' => fn ($q, $v) => $q->where('event', $v),
            ],
        );

        if ($query->cursor !== null) {
            return $builder->cursorPaginate($query->perPage, cursor: $query->cursor);
        }

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function find(int $id): Audit
    {
        /** @var class-string<Audit&\Illuminate\Database\Eloquent\Model> $auditModel */
        $auditModel = config('audit.implementation', Audit::class);

        return $auditModel::query()->with('user:id,name,email')->findOrFail($id);
    }
}
