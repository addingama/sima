<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AutoPortalDonorLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
        config([
            'sima.portal.auto_create_user' => true,
            'sima.portal.default_password' => 'password',
        ]);
    }

    #[Test]
    public function creating_donor_with_phone_auto_creates_portal_user(): void
    {
        $this->actingAsRole('bendahara');

        $response = $this->postJson('/api/donors', [
            'name' => 'Siti Portal',
            'type' => 'individu',
            'phone' => '0812-3456-7890',
            'is_active' => true,
        ])->assertCreated();

        $userId = $response->json('data.user_id');
        $this->assertNotNull($userId);

        $user = User::findOrFail($userId);
        $this->assertTrue($user->hasRole('donatur'));
        $this->assertSame('081234567890', $user->phone);
        $this->assertTrue(Hash::check('password', $user->password));

        $this->postJson('/api/login', [
            'login' => '081234567890',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
    }

    #[Test]
    public function creating_donor_with_email_allows_login_by_email(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/donors', [
            'name' => 'Andi Portal',
            'type' => 'individu',
            'email' => 'andi.portal@example.com',
            'is_active' => true,
        ])->assertCreated();

        $this->postJson('/api/login', [
            'login' => 'andi.portal@example.com',
            'password' => 'password',
        ])->assertOk();

        // Kompatibilitas field email lama
        $this->postJson('/api/login', [
            'email' => 'andi.portal@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    #[Test]
    public function create_donor_requires_email_or_phone(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/donors', [
            'name' => 'Tanpa Kontak',
            'type' => 'individu',
            'is_active' => true,
        ])->assertStatus(422);
    }
}
