<?php

namespace App\Services\User;

use App\Domains\Audit\Services\AuditLogService;
use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /** @return array{roles: Collection<int, Role>, permissions: array<int, string>} */
    public function index(): array
    {
        $order = array_flip(UserRole::values());
        $roleUserCounts = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', (new User)->getMorphClass())
            ->selectRaw('role_id, COUNT(*) as aggregate')
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id');

        $roles = Role::query()
            ->whereIn('name', UserRole::values())
            ->with('permissions:id,name')
            ->get()
            ->each(fn (Role $role) => $role->setAttribute(
                'users_count',
                (int) ($roleUserCounts[$role->id] ?? 0),
            ))
            ->sortBy(fn (Role $role): int => $order[$role->name] ?? PHP_INT_MAX)
            ->values();

        return [
            'roles' => $roles,
            'permissions' => array_values(config('sima.permissions', [])),
        ];
    }

    /** @param array<int, string> $permissions */
    public function syncPermissions(Role $role, array $permissions, User $actor): Role
    {
        if ($role->name === UserRole::ADMIN->value) {
            throw new DomainException('Permission role Administrator tidak dapat diubah.');
        }

        if (! in_array($role->name, UserRole::values(), true)) {
            throw new DomainException('Role tidak dikelola oleh SIMA.');
        }

        return DB::transaction(function () use ($role, $permissions, $actor): Role {
            $before = $role->permissions()->pluck('name')->sort()->values()->all();
            $normalized = collect($permissions)->unique()->sort()->values()->all();

            $role->syncPermissions($normalized);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->audit->log(
                $role,
                'role_permissions_updated',
                ['permissions' => $before],
                ['permissions' => $normalized],
                $actor,
                'rbac',
            );

            return $role->refresh()->load('permissions:id,name');
        });
    }
}
