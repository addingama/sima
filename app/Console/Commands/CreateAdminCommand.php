<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'sima:create-admin
                            {--name= : Nama administrator}
                            {--email= : Email login}
                            {--password= : Password (min. 8 karakter)}';

    protected $description = 'Buat akun administrator produksi (tanpa seeder demo).';

    public function handle(): int
    {
        app(RolePermissionSeeder::class)->run();

        $name = $this->option('name') ?: $this->ask('Nama administrator');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (min. 8 karakter)');

        $existing = User::query()->where('email', $email)->first();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
        if ($existing === null) {
            $rules['email'][] = 'unique:users,email';
        }

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            $rules,
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if ($existing !== null) {
            if ($existing->roles()->exists()) {
                $this->error('Email sudah dipakai.');

                return self::FAILURE;
            }

            $existing->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ])->save();
            $existing->syncRoles([UserRole::ADMIN->value]);
            $this->info("Peran admin dipasang pada akun yang sudah ada: {$existing->email} (id={$existing->id})");
            $this->line('Jangan gunakan akun *@sima.test di produksi.');

            return self::SUCCESS;
        }

        $user = DB::transaction(function () use ($name, $email, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([UserRole::ADMIN->value]);

            return $user;
        });

        $this->info("Administrator dibuat: {$user->email} (id={$user->id})");
        $this->line('Jangan gunakan akun *@sima.test di produksi.');

        return self::SUCCESS;
    }
}
