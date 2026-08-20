<?php

namespace App\Services\User;

use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): User
    {
        $role = $this->normalizeRole($data['role'] ?? null);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$role]);

        return $user->refresh();
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data, User $actor): User
    {
        $payload = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : null,
            'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : null,
        ], fn ($value) => $value !== null);

        if (array_key_exists('is_active', $payload) && $payload['is_active'] === false) {
            $this->assertCanDeactivate($user, $actor);
        }

        $user->update($payload);

        if (isset($data['role'])) {
            $this->syncRole($user, $this->normalizeRole($data['role']), $actor);
        }

        if (array_key_exists('is_active', $payload) && $payload['is_active'] === false) {
            $user->tokens()->delete();
        }

        return $user->refresh();
    }

    public function syncRole(User $user, string $role, User $actor): User
    {
        $this->assertCanChangeRole($user, $role, $actor);

        $user->syncRoles([$role]);

        return $user->refresh();
    }

    public function resetPassword(User $user, string $password, User $actor): User
    {
        $user->update(['password' => $password]);
        $user->tokens()->delete();

        return $user->refresh();
    }

    public function deactivate(User $user, User $actor): void
    {
        $this->assertCanDeactivate($user, $actor);

        $user->update(['is_active' => false]);
        $user->tokens()->delete();
    }

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            User::query()->with('roles'),
            $query,
            searchColumns: ['name', 'email', 'phone'],
            sortable: ['name', 'email', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterCallbacks: [
                'is_active' => fn ($q, $v) => $q->where('is_active', filter_var($v, FILTER_VALIDATE_BOOLEAN)),
                'role' => fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v)),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    private function normalizeRole(mixed $role): string
    {
        $value = (string) $role;

        if (! in_array($value, UserRole::values(), true)) {
            throw ValidationException::withMessages([
                'role' => ['Role tidak valid.'],
            ]);
        }

        return $value;
    }

    private function assertCanDeactivate(User $target, User $actor): void
    {
        if ($actor->id === $target->id) {
            throw new DomainException('Tidak dapat menonaktifkan akun sendiri.');
        }

        $this->assertAdminCapacityIfRemovingAdmin($target, null);
    }

    private function assertCanChangeRole(User $target, string $newRole, User $actor): void
    {
        if ($actor->id === $target->id && $target->hasRole(UserRole::ADMIN->value) && $newRole !== UserRole::ADMIN->value) {
            throw new DomainException('Tidak dapat menghapus role admin dari akun sendiri.');
        }

        $this->assertAdminCapacityIfRemovingAdmin($target, $newRole);
    }

    private function assertAdminCapacityIfRemovingAdmin(User $target, ?string $newRole): void
    {
        if (! $target->hasRole(UserRole::ADMIN->value) || ! $target->is_active) {
            return;
        }

        if ($newRole === UserRole::ADMIN->value) {
            return;
        }

        $remaining = User::role(UserRole::ADMIN->value)
            ->where('is_active', true)
            ->whereKeyNot($target->id)
            ->count();

        if ($remaining < 1) {
            throw new DomainException('Minimal harus ada satu administrator aktif.');
        }
    }
}
