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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DonorService
{
    public function __construct(private readonly DocumentNumberService $documentNumbers) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Donor
    {
        return DB::transaction(function () use ($data, $actor): Donor {
            $code = trim((string) ($data['code'] ?? ''));
            if ($code === '') {
                $code = $this->documentNumbers->next('DON');
            }

            unset($data['code']);

            if (! empty($data['user_id'])) {
                $this->assertPortalUser($data['user_id']);
                $data['user_id'] = (int) $data['user_id'];
            } else {
                $data['user_id'] = $this->maybeCreatePortalUser($data)?->id;
            }

            return Donor::create([
                ...$data,
                'code' => $code,
                'created_by' => $actor->id,
            ])->load('user:id,name,email,phone');
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Donor $donor, array $data): Donor
    {
        return DB::transaction(function () use ($donor, $data): Donor {
            unset($data['code']);

            if (array_key_exists('user_id', $data)) {
                if ($data['user_id'] !== null && $data['user_id'] !== '') {
                    $this->assertPortalUser($data['user_id']);
                    $data['user_id'] = (int) $data['user_id'];
                } else {
                    $data['user_id'] = null;
                }
            } elseif ($donor->user_id === null) {
                $merged = [
                    'name' => $data['name'] ?? $donor->name,
                    'email' => array_key_exists('email', $data) ? $data['email'] : $donor->email,
                    'phone' => array_key_exists('phone', $data) ? $data['phone'] : $donor->phone,
                ];
                $created = $this->maybeCreatePortalUser($merged);
                if ($created) {
                    $data['user_id'] = $created->id;
                }
            }

            $donor->update($data);

            if ($donor->user_id) {
                $this->syncLinkedPortalUser($donor->fresh());
            }

            return $donor->refresh()->load('user:id,name,email,phone');
        });
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
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'label' => $user->phone
                    ? "{$user->name} ({$user->email} / {$user->phone})"
                    : "{$user->name} ({$user->email})",
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $donorData */
    private function maybeCreatePortalUser(array $donorData): ?User
    {
        if (! config('sima.portal.auto_create_user', true)) {
            return null;
        }

        $email = trim((string) ($donorData['email'] ?? ''));
        $phone = $this->normalizePhone((string) ($donorData['phone'] ?? ''));
        $name = trim((string) ($donorData['name'] ?? ''));

        if ($email === '' && $phone === '') {
            return null;
        }

        if ($email !== '' && User::query()->where('email', $email)->exists()) {
            throw new DomainException(
                "Email \"{$email}\" sudah dipakai akun lain. Kosongkan email, ganti email, atau tautkan Akun Login Portal secara manual."
            );
        }

        if ($phone !== '' && User::query()->where('phone', $phone)->exists()) {
            throw new DomainException(
                "Nomor HP \"{$phone}\" sudah dipakai akun lain. Ganti nomor atau tautkan Akun Login Portal secara manual."
            );
        }

        $loginEmail = $email !== '' ? $email : $this->syntheticPortalEmail($phone);

        $user = User::create([
            'name' => $name !== '' ? $name : 'Donatur',
            'email' => $loginEmail,
            'phone' => $phone !== '' ? $phone : null,
            'password' => Hash::make((string) config('sima.portal.default_password', 'password')),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([UserRole::DONATUR->value]);

        return $user;
    }

    private function syncLinkedPortalUser(Donor $donor): void
    {
        $user = $donor->user;
        if (! $user || ! $user->hasRole(UserRole::DONATUR->value)) {
            return;
        }

        $phone = $this->normalizePhone((string) ($donor->phone ?? ''));
        $updates = ['name' => $donor->name];

        if ($donor->email && $donor->email !== $user->email) {
            $taken = User::query()->where('email', $donor->email)->where('id', '!=', $user->id)->exists();
            if (! $taken) {
                $updates['email'] = $donor->email;
            }
        }

        if ($phone !== '') {
            $taken = User::query()->where('phone', $phone)->where('id', '!=', $user->id)->exists();
            if (! $taken) {
                $updates['phone'] = $phone;
            }
        }

        $user->update($updates);
    }

    private function syntheticPortalEmail(string $phoneDigits): string
    {
        $base = 'portal.'.$phoneDigits.'@donatur.local';
        if (! User::query()->where('email', $base)->exists()) {
            return $base;
        }

        return 'portal.'.$phoneDigits.'.'.Str::lower(Str::random(4)).'@donatur.local';
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
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
