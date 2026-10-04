<?php

namespace Tests\Feature\Api;

use App\Models\Donor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private const READ_ENDPOINTS = [
        'donor.view' => '/api/donors',
        'vendor.view' => '/api/vendors',
        'fund.view' => '/api/funds',
        'account.view' => '/api/accounts',
        'program.view' => '/api/programs',
        'receipt.view' => '/api/receipts',
        'disbursement.view' => '/api/disbursements',
        'bankfee.view' => '/api/bank-fees',
        'transfer.view' => '/api/account-transfers',
        'reconciliation.view' => '/api/bank-reconciliations',
        'liability.view' => '/api/liabilities',
        'opening.view' => '/api/opening-balances',
        'grant.view' => '/api/grant-applications',
        'audit.view' => '/api/audits',
        'report.view' => '/api/reports/fund-balances',
        'user.manage' => '/api/users',
        'portal.view' => '/api/portal/summary',
    ];

    /** @var array<string, array{string, string}> */
    private const MUTATION_ENDPOINTS = [
        'donor.manage' => ['POST', '/api/donors'],
        'vendor.manage' => ['POST', '/api/vendors'],
        'fund.manage' => ['POST', '/api/funds'],
        'account.manage' => ['POST', '/api/accounts'],
        'program.manage' => ['POST', '/api/programs'],
        'receipt.create' => ['POST', '/api/receipts'],
        'disbursement.create' => ['POST', '/api/disbursements'],
        'grant.create' => ['POST', '/api/grant-applications'],
        'bankfee.manage' => ['POST', '/api/bank-fees'],
        'transfer.manage' => ['POST', '/api/account-transfers'],
        'opening.manage' => ['POST', '/api/opening-balances'],
        'reconciliation.manage' => ['POST', '/api/bank-reconciliations'],
        'liability.manage' => ['POST', '/api/liabilities'],
        'attachment.manage' => ['POST', '/api/attachments'],
        'user.manage' => ['POST', '/api/users'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return [
            'admin' => ['admin'],
            'asisten_bendahara' => ['asisten_bendahara'],
            'bendahara' => ['bendahara'],
            'verifikator' => ['verifikator'],
            'petugas_bantuan' => ['petugas_bantuan'],
            'ketua' => ['ketua'],
            'auditor' => ['auditor'],
            'donatur' => ['donatur'],
        ];
    }

    #[DataProvider('roles')]
    public function test_read_endpoints_follow_the_permission_matrix_for_every_role(string $role): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        if ($role === 'donatur') {
            Donor::create([
                'user_id' => $user->id,
                'code' => 'DON-ROLE-MATRIX',
                'name' => 'Donatur Role Matrix',
                'type' => 'individu',
                'is_active' => true,
            ]);
        }

        Sanctum::actingAs($user);

        foreach (self::READ_ENDPOINTS as $permission => $endpoint) {
            $response = $this->getJson($endpoint);

            if ($user->can($permission)) {
                $this->assertNotSame(
                    403,
                    $response->status(),
                    "Role {$role} seharusnya dapat mengakses {$endpoint} dengan {$permission}."
                );
            } else {
                $response->assertForbidden();
            }
        }
    }

    #[DataProvider('roles')]
    public function test_mutation_endpoints_follow_the_permission_matrix_for_every_role(string $role): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        foreach (self::MUTATION_ENDPOINTS as $permission => [$method, $endpoint]) {
            $response = $this->json($method, $endpoint, []);

            if ($user->can($permission)) {
                $this->assertNotSame(
                    403,
                    $response->status(),
                    "Role {$role} seharusnya melewati middleware {$permission} pada {$method} {$endpoint}."
                );
            } else {
                $response->assertForbidden();
            }
        }
    }

    #[DataProvider('roles')]
    public function test_only_admin_can_manage_role_permissions(string $role): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        Sanctum::actingAs($user);
        $targetRole = Role::findByName('bendahara', 'web');

        $response = $this->putJson("/api/roles/{$targetRole->id}/permissions", [
            'permissions' => ['fund.view'],
        ]);

        if ($role === 'admin') {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }

    public function test_unauthenticated_and_inactive_users_are_rejected(): void
    {
        $this->getJson('/api/receipts')->assertUnauthorized();

        $inactive = User::factory()->create(['is_active' => false]);
        $inactive->assignRole('admin');

        $this->postJson('/api/login', [
            'login' => $inactive->email,
            'password' => 'password',
            'device_name' => 'role-matrix-test',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.fields.login.0', 'Akun tidak aktif. Hubungi administrator.');
    }

    public function test_seeded_roles_match_the_configured_permission_contract(): void
    {
        foreach (config('sima.roles') as $role => $configuredPermissions) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $actual = $user->getAllPermissions()->pluck('name')->sort()->values()->all();
            $expected = $configuredPermissions === ['*']
                ? collect(config('sima.permissions'))->sort()->values()->all()
                : collect($configuredPermissions)->sort()->values()->all();

            $this->assertSame($expected, $actual, "Permission role {$role} menyimpang dari config/sima.php.");
        }
    }
}
