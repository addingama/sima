<?php

namespace App\Services\Master;

use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Models\Donor;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DonorService
{
    public function __construct(private readonly DocumentNumberService $documentNumbers) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Donor
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = $this->documentNumbers->next('DON');
        }

        unset($data['code']);
        $this->assertPortalUser($data['user_id'] ?? null);

        return Donor::create([
            ...$data,
            'code' => $code,
            'created_by' => $actor->id,
        ])->load('user:id,name,email');
    }

    /** @param  array<string, mixed>  $data */
    public function update(Donor $donor, array $data): Donor
    {
        unset($data['code']);

        if (array_key_exists('user_id', $data)) {
            $this->assertPortalUser($data['user_id']);
            $data['user_id'] = $data['user_id'] !== null && $data['user_id'] !== ''
                ? (int) $data['user_id']
                : null;
        }

        $donor->update($data);

        return $donor->refresh()->load('user:id,name,email');
    }

    public function delete(Donor $donor): void
    {
        $donor->delete();
    }

    /**
     * Opsi akun login untuk form tautan portal (role donatur).
     *
     * @return Collection<int, array{id: int, name: string, email: string, label: string}>
     */
    public function portalLoginOptions(): Collection
    {
        return User::role(UserRole::DONATUR->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'label' => "{$user->name} ({$user->email})",
            ])
            ->values();
    }

    private function assertPortalUser(mixed $userId): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $user = User::query()->find((int) $userId);
        if (! $user) {
            throw new DomainException('Akun login tidak ditemukan.');
        }

        if (! $user->hasRole(UserRole::DONATUR->value)) {
            throw new DomainException('Akun login portal harus memiliki role donatur.');
        }
    }

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            Donor::query(),
            $query,
            searchColumns: ['name', 'code', 'email', 'phone'],
            sortable: ['name', 'code', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
