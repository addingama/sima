<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

class RolePermissionIndexResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{roles: iterable<Role>, permissions: array<int, string>} $data */
        $data = $this->resource;

        return [
            'roles' => collect($data['roles'])->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => UserRole::tryFrom($role->name)?->label() ?? $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                'users_count' => $role->users_count,
                'is_locked' => $role->name === UserRole::ADMIN->value,
            ])->values()->all(),
            'permissions' => collect($data['permissions'])->sort()->values()->all(),
        ];
    }
}
