<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function admin_can_create_user_with_role(): void
    {
        $this->actingAsRole('admin');

        $response = $this->postJson('/api/users', [
            'name' => 'Bendahara Baru',
            'email' => 'bendahara@lembaga.org',
            'phone' => '08123456789',
            'password' => 'password123',
            'role' => 'bendahara',
            'is_active' => true,
        ])->assertCreated();

        $response->assertJsonPath('data.email', 'bendahara@lembaga.org');
        $response->assertJsonPath('data.roles.0', 'bendahara');

        $user = User::where('email', 'bendahara@lembaga.org')->first();
        $this->assertTrue($user->hasRole('bendahara'));
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    #[Test]
    public function admin_can_list_and_update_users(): void
    {
        $this->actingAsRole('admin');

        $create = $this->postJson('/api/users', [
            'name' => 'Auditor Baru',
            'email' => 'auditor@lembaga.org',
            'password' => 'password123',
            'role' => 'auditor',
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'auditor@lembaga.org']);

        $this->putJson("/api/users/{$id}", [
            'name' => 'Auditor Utama',
            'phone' => '08000000000',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Auditor Utama')
            ->assertJsonPath('data.phone', '08000000000');
    }

    #[Test]
    public function admin_can_sync_user_role_via_dedicated_endpoint(): void
    {
        $this->actingAsRole('admin');

        $user = User::factory()->create(['email' => 'staff@lembaga.org']);
        $user->syncRoles(['bendahara']);

        $this->putJson("/api/users/{$user->id}/roles", [
            'role' => 'verifikator',
        ])->assertOk()
            ->assertJsonPath('data.roles.0', 'verifikator');
    }

    #[Test]
    public function admin_can_reset_password_and_deactivate_user(): void
    {
        $this->actingAsRole('admin');

        $user = User::factory()->create(['email' => 'inactive@lembaga.org', 'is_active' => true]);
        $user->syncRoles(['bendahara']);
        $user->createToken('test');

        $this->postJson("/api/users/{$user->id}/reset-password", [
            'password' => 'newpassword99',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword99', $user->password));
        $this->assertSame(0, $user->tokens()->count());

        $this->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Pengguna dinonaktifkan.');

        $this->assertFalse($user->fresh()->is_active);
    }

    #[Test]
    public function admin_cannot_deactivate_self(): void
    {
        $admin = $this->actingAsRole('admin');

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    #[Test]
    public function bendahara_cannot_manage_users(): void
    {
        $this->actingAsRole('bendahara');

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [
            'name' => 'X',
            'email' => 'x@test.org',
            'password' => 'password123',
            'role' => 'auditor',
        ])->assertForbidden();
    }
}
