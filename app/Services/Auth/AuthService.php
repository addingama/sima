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
    public function login(string $email, string $password, ?string $deviceName, string $ip): array
    {
        $throttleKey = Str::lower($email).'|'.$ip;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."],
            ])->status(429);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak valid.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun tidak aktif. Hubungi administrator.'],
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
}
