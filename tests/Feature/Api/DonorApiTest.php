<?php

namespace Tests\Feature\Api;

use App\Models\Donor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SimaTestHelpers;
use Tests\TestCase;

class DonorApiTest extends TestCase
{
    use RefreshDatabase;
    use SimaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    public function test_store_auto_generates_donor_code_when_omitted(): void
    {
        $this->actingAsRole('bendahara');

        $year = (int) date('Y');

        $response = $this->postJson('/api/donors', [
            'name' => 'Ahmad Zakat',
            'type' => 'individu',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Ahmad Zakat');

        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^DON\/'.$year.'\/\d{6}$/', $code);

        $this->assertDatabaseHas('donors', [
            'code' => $code,
            'name' => 'Ahmad Zakat',
        ]);
    }

    public function test_store_accepts_explicit_code_when_provided(): void
    {
        $this->actingAsRole('bendahara');

        $response = $this->postJson('/api/donors', [
            'code' => 'DON-LEGACY-001',
            'name' => 'Yayasan Contoh',
            'type' => 'lembaga',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'DON-LEGACY-001');
    }

    public function test_update_does_not_change_donor_code(): void
    {
        $user = $this->actingAsRole('bendahara');

        $donor = Donor::create([
            'code' => 'DON/2026/000099',
            'name' => 'Donatur Lama',
            'type' => 'individu',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->putJson("/api/donors/{$donor->id}", [
            'code' => 'DON/2026/999999',
            'name' => 'Donatur Baru',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'DON/2026/000099')
            ->assertJsonPath('data.name', 'Donatur Baru');
    }
}
