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

    public function test_authenticated_user_can_change_own_password_without_user_manage(): void
    {
        $user = $this->user('own-password@sima.test', true);
        $user->assignRole('donatur');

        $current = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'current-device',
        ])->assertOk()->json('data.token');

        $other = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'other-device',
        ])->assertOk()->json('data.token');

        $this->withToken($current)->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertOk()
            ->assertJsonPath('message', 'Password berhasil diubah.');

        $this->assertTrue(Hash::check('password-baru', $user->refresh()->password));

        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/me')->assertUnauthorized();

        $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable();

        $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password-baru',
            'device_name' => 'after-change',
        ])->assertOk();
    }

    public function test_own_password_change_rejects_invalid_input(): void
    {
        $user = $this->user('invalid-password@sima.test', true);
        $token = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'invalid-password',
        ])->assertOk()->json('data.token');

        $this->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertUnauthorized();

        $this->withToken($token)->putJson('/api/me/password', [
            'current_password' => 'salah',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.fields.current_password.0', 'Password saat ini tidak sesuai.');

        $this->withToken($token)->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'password-baru',
            'password_confirmation' => 'tidak-sama',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.fields.password.0', 'Konfirmasi password tidak sama.');

        $this->withToken($token)->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.fields.password.0', 'Password baru harus berbeda dari password saat ini.');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
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
