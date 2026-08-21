<?php

namespace App\Services\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /** @return array{token: string, user: array<string, mixed>} */
    public function login(string $login, string $password, ?string $deviceName, string $ip): array
    {
        $login = trim($login);
        $throttleKey = Str::lower($login).'|'.$ip;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => ["Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."],
            ])->status(429);
        }

        $user = $this->findByLogin($login);

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            throw ValidationException::withMessages([
                'login' => ['Kredensial tidak valid.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Akun tidak aktif. Hubungi administrator.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken($deviceName ?? 'sima-spa')->plainTextToken;

        return [
            'token' => $token,
            'user' => (new UserResource($user->load('roles')))->resolve(),
        ];
    }

    /** @return array<string, mixed> */
    public function me(User $user): array
    {
        return (new UserResource($user->load('roles')))->resolve();
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    private function findByLogin(string $login): ?User
    {
        $normalizedPhone = $this->normalizePhone($login);

        return User::query()
            ->where(function ($query) use ($login, $normalizedPhone): void {
                $query->where('email', $login);

                if ($normalizedPhone !== '') {
                    $query->orWhere('phone', $login)->orWhere('phone', $normalizedPhone);
                }
            })
            ->first();
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits;
    }
}
