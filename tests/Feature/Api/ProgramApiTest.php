<?php

namespace Tests\Feature\Api;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SimaTestHelpers;
use Tests\TestCase;

class ProgramApiTest extends TestCase
{
    use RefreshDatabase;
    use SimaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    public function test_store_auto_generates_event_code_when_omitted(): void
    {
        $this->actingAsRole('bendahara');

        $year = (int) date('Y');

        $response = $this->postJson('/api/programs', [
            'name' => 'Khitan Massal',
            'event_type' => 'planned',
            'budget' => '50000000.00',
            'status' => 'planned',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Khitan Massal')
            ->assertJsonPath('data.event_type', 'planned')
            ->assertJsonPath('data.budget', '50000000.00');

        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^EVT\/'.$year.'\/\d{6}$/', $code);

        $this->assertDatabaseHas('programs', [
            'code' => $code,
            'name' => 'Khitan Massal',
            'event_type' => 'planned',
        ]);
    }

    public function test_store_supports_emergency_event_without_budget(): void
    {
        $this->actingAsRole('bendahara');

        $response = $this->postJson('/api/programs', [
            'name' => 'Donasi Bencana Alam',
            'event_type' => 'emergency',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Donasi Bencana Alam')
            ->assertJsonPath('data.event_type', 'emergency')
            ->assertJsonPath('data.budget', null);
    }

    public function test_update_does_not_change_event_code(): void
    {
        $user = $this->actingAsRole('bendahara');

        $program = Program::create([
            'code' => 'EVT/2026/000099',
            'name' => 'Event Lama',
            'event_type' => 'campaign',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->putJson("/api/programs/{$program->id}", [
            'code' => 'EVT/2026/999999',
            'name' => 'Event Baru',
            'event_type' => 'routine',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'EVT/2026/000099')
            ->assertJsonPath('data.name', 'Event Baru')
            ->assertJsonPath('data.event_type', 'routine');
    }
}
