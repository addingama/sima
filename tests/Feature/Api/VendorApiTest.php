<?php

namespace Tests\Feature\Api;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SimaTestHelpers;
use Tests\TestCase;

class VendorApiTest extends TestCase
{
    use RefreshDatabase;
    use SimaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    public function test_store_auto_generates_vendor_code_when_omitted(): void
    {
        $this->actingAsRole('bendahara');

        $year = (int) date('Y');

        $response = $this->postJson('/api/vendors', [
            'name' => 'PT Sumber Rezeki',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'PT Sumber Rezeki');

        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^VND\/'.$year.'\/\d{6}$/', $code);

        $this->assertDatabaseHas('vendors', [
            'code' => $code,
            'name' => 'PT Sumber Rezeki',
        ]);
    }

    public function test_store_accepts_explicit_code_when_provided(): void
    {
        $this->actingAsRole('bendahara');

        $response = $this->postJson('/api/vendors', [
            'code' => 'VND-LEGACY-001',
            'name' => 'Vendor Lama',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'VND-LEGACY-001');
    }

    public function test_update_does_not_change_vendor_code(): void
    {
        $user = $this->actingAsRole('bendahara');

        $vendor = Vendor::create([
            'code' => 'VND/2026/000099',
            'name' => 'Vendor Lama',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->putJson("/api/vendors/{$vendor->id}", [
            'code' => 'VND/2026/999999',
            'name' => 'Vendor Baru',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'VND/2026/000099')
            ->assertJsonPath('data.name', 'Vendor Baru');
    }
}
