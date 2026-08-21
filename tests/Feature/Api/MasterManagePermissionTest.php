<?php

namespace Tests\Feature\Api;

use App\Models\Fund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MasterManagePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function bendahara_can_create_fund_and_account(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/funds', [
            'code' => 'ZKT-TEST',
            'name' => 'Zakat Uji',
            'type' => 'restricted',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'ZKT-TEST');

        $this->postJson('/api/accounts', [
            'code' => 'KAS-TEST',
            'name' => 'Kas Uji',
            'type' => 'cash',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'KAS-TEST');
    }

    #[Test]
    public function bendahara_cannot_update_system_fund(): void
    {
        $this->actingAsRole('bendahara');

        $system = Fund::findBySystemKey(Fund::KEY_OPERATIONAL);
        $this->assertNotNull($system);

        $this->putJson("/api/funds/{$system->id}", [
            'name' => 'Jangan Diubah',
        ])->assertForbidden();
    }

    #[Test]
    public function verifikator_cannot_create_fund(): void
    {
        $this->actingAsRole('verifikator');

        $this->postJson('/api/funds', [
            'code' => 'NOPE',
            'name' => 'Tidak Boleh',
            'type' => 'restricted',
            'is_active' => true,
        ])->assertForbidden();
    }
}
