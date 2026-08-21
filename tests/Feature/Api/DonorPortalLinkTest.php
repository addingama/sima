<?php

namespace Tests\Feature\Api;

use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DonorPortalLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function bendahara_can_list_portal_login_options(): void
    {
        $this->actingAsRole('bendahara');

        $donatur = User::factory()->create(['is_active' => true, 'email' => 'portal@link.test']);
        $donatur->assignRole('donatur');

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('bendahara');

        $this->getJson('/api/donors/portal-login-options')
            ->assertOk()
            ->assertJsonFragment(['email' => 'portal@link.test'])
            ->assertJsonMissing(['email' => $staff->email]);
    }

    #[Test]
    public function bendahara_can_link_donatur_user_on_donor(): void
    {
        $bendahara = $this->actingAsRole('bendahara');

        $donatur = User::factory()->create(['is_active' => true]);
        $donatur->assignRole('donatur');

        $donor = Donor::create([
            'code' => 'DON/TEST/001',
            'name' => 'Donatur Uji',
            'type' => 'individu',
            'is_active' => true,
            'created_by' => $bendahara->id,
        ]);

        $this->putJson("/api/donors/{$donor->id}", [
            'user_id' => $donatur->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.user_id', $donatur->id)
            ->assertJsonPath('data.user.email', $donatur->email);

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'user_id' => $donatur->id,
        ]);
    }

    #[Test]
    public function cannot_link_non_donatur_user(): void
    {
        $bendahara = $this->actingAsRole('bendahara');
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('ketua');

        $donor = Donor::create([
            'code' => 'DON/TEST/002',
            'name' => 'Donatur Uji 2',
            'type' => 'individu',
            'is_active' => true,
            'created_by' => $bendahara->id,
        ]);

        $this->putJson("/api/donors/{$donor->id}", [
            'user_id' => $staff->id,
        ])->assertStatus(422);
    }
}
