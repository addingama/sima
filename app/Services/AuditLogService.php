<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Audit;

/**
 * Pencatatan audit untuk AKSI bisnis penting (di luar diff field otomatis owen-it):
 * create, update draft, submit, approve, reject, void/reversal, attachment upload.
 *
 * Menulis ke tabel yang sama dengan owen-it (audit_logs) agar audit trail terpusat.
 * Pemetaan kolom: action -> event, entity -> auditable, before -> old_values, after -> new_values.
 */
class AuditLogService
{
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
        $actor ??= (Auth::user() instanceof User ? Auth::user() : null);

        /** @var class-string<Audit&Model> $auditModel */
        $auditModel = config('audit.implementation', \OwenIt\Auditing\Models\Audit::class);

        $request = request();

        return $auditModel::create([
            'user_type' => $actor ? $actor->getMorphClass() : null,
            'user_id' => $actor?->getKey(),
            'event' => $action,
            'auditable_type' => $entity->getMorphClass(),
            'auditable_id' => $entity->getKey(),
            'old_values' => $before ?? [],
            'new_values' => $after ?? [],
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'tags' => $tags ?? 'action',
        ]);
    }
}
