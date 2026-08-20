<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
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
        $name = $this->option('name') ?: $this->ask('Nama administrator');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (min. 8 karakter)');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([UserRole::ADMIN->value]);

        $this->info("Administrator dibuat: {$user->email} (id={$user->id})");
        $this->line('Jangan gunakan akun *@sima.test di produksi.');

        return self::SUCCESS;
    }
}
