<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_creates_roles_and_assigns_admin(): void
    {
        $this->assertFalse(Role::query()->where('name', 'admin')->exists());

        $this->artisan('sima:create-admin', [
            '--name' => 'Addin Gama Bertaqwa',
            '--email' => 'addingama@gmail.com',
            '--password' => 'password12',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'addingama@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue(Role::query()->where('name', 'bendahara')->exists());
    }

    public function test_create_admin_attaches_role_when_account_exists_without_role(): void
    {
        $user = User::factory()->create([
            'email' => 'addingama@gmail.com',
            'name' => 'Sementara',
        ]);

        $this->artisan('sima:create-admin', [
            '--name' => 'Addin Gama Bertaqwa',
            '--email' => 'addingama@gmail.com',
            '--password' => 'password12',
        ])->assertSuccessful();

        $user->refresh();
        $this->assertSame('Addin Gama Bertaqwa', $user->name);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_create_admin_rejects_email_that_already_has_a_role(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['email' => 'addingama@gmail.com']);
        $user->assignRole('bendahara');

        $this->artisan('sima:create-admin', [
            '--name' => 'Addin',
            '--email' => 'addingama@gmail.com',
            '--password' => 'password12',
        ])->assertFailed();

        $user->refresh();
        $this->assertTrue($user->hasRole('bendahara'));
        $this->assertFalse($user->hasRole('admin'));
    }
}
