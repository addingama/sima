<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Satu akun contoh per role (password: "password").
        $accounts = [
            ['Administrator SIMA', 'admin@sima.test', UserRole::ADMIN],
            ['Bendahara', 'bendahara@sima.test', UserRole::BENDAHARA],
            ['Verifikator', 'verifikator@sima.test', UserRole::VERIFIKATOR],
            ['Ketua', 'ketua@sima.test', UserRole::KETUA],
            ['Auditor', 'auditor@sima.test', UserRole::AUDITOR],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$role->value]);
        }
    }
}
