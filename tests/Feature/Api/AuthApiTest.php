<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_returns_token_and_current_role(): void
    {
        $user = $this->user('bendahara@sima.test', true);
        $user->assignRole('bendahara');

        $response = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'auth-test',
        ])->assertOk()
            ->assertJsonPath('data.user.roles.0', 'bendahara');

        $token = $response->json('data.token');
        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_invalid_password_and_inactive_account_do_not_issue_token(): void
    {
        $active = $this->user('active@sima.test', true);
        $inactive = $this->user('inactive@sima.test', false);

        $this->postJson('/api/login', [
            'login' => $active->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.fields.login.0', 'Kredensial tidak valid.')
            ->assertJsonMissingPath('data.token');

        $this->postJson('/api/login', [
            'login' => $inactive->email,
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.fields.login.0', 'Akun tidak aktif. Hubungi administrator.')
            ->assertJsonMissingPath('data.token');
    }

    public function test_repeated_invalid_login_is_rate_limited(): void
    {
        $user = $this->user('limited@sima.test', true);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', [
                'login' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests()
            ->assertJsonPath('errors.code', 'validation_error');
    }

    public function test_logout_invalidates_the_current_token(): void
    {
        $user = $this->user('logout@sima.test', true);
        $user->assignRole('auditor');

        $token = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'logout-test',
        ])->assertOk()->json('data.token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => (int) str($token)->before('|')->toString(),
        ]);

        // Request feature test berbagi container aplikasi; paksa guard membaca ulang token yang sudah dihapus.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    private function user(string $email, bool $active): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => $active,
        ]);
    }
}
