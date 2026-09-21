<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_list_roles_and_permissions(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $response = $this->getJson('/api/roles');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.roles.0.name', 'admin')
            ->assertJsonPath('data.roles.0.is_locked', true)
            ->assertJsonFragment(['name' => 'bendahara'])
            ->assertJsonFragment(['receipt.approve']);
    }

    public function test_non_admin_cannot_manage_role_permissions(): void
    {
        Sanctum::actingAs($this->userWithRole('bendahara'));
        $role = Role::findByName('verifikator', 'web');

        $this->getJson('/api/roles')->assertForbidden();
        $this->putJson("/api/roles/{$role->id}/permissions", [
            'permissions' => ['grant.view'],
        ])->assertForbidden();
    }

    public function test_admin_can_sync_non_admin_role_permissions_and_audit_change(): void
    {
        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin);
        $role = Role::findByName('verifikator', 'web');

        $response = $this->putJson("/api/roles/{$role->id}/permissions", [
            'permissions' => ['grant.view', 'grant.verify'],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Permission role berhasil diperbarui.');
        $this->assertSame(['grant.verify', 'grant.view'], $role->fresh()->permissions->pluck('name')->sort()->values()->all());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'role_permissions_updated',
            'auditable_id' => $role->id,
            'tags' => 'rbac',
        ]);
    }

    public function test_admin_role_is_locked(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));
        $role = Role::findByName('admin', 'web');

        $this->putJson("/api/roles/{$role->id}/permissions", [
            'permissions' => ['user.manage'],
        ])->assertForbidden();
    }

    public function test_permissions_must_be_known_and_distinct(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));
        $role = Role::findByName('auditor', 'web');

        $this->putJson("/api/roles/{$role->id}/permissions", [
            'permissions' => ['unknown.permission', 'unknown.permission'],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code', 'validation_error')
            ->assertJsonStructure([
                'errors' => ['fields' => ['permissions.0', 'permissions.1']],
            ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
