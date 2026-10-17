<?php

namespace Tests\Feature\Api;

use App\Enums\PettyCashFloatPurpose;
use App\Enums\PettyCashFloatStatus;
use App\Models\PettyCashCategory;
use App\Models\PettyCashFloat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PettyCashApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function bendahara_can_manage_categories(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/petty-cash-categories', [
            'code' => 'internet',
            'name' => 'Internet',
            'sort_order' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'INTERNET')
            ->assertJsonPath('data.name', 'Internet');

        $id = PettyCashCategory::query()->firstOrFail()->id;

        $this->putJson("/api/petty-cash-categories/{$id}", [
            'name' => 'Internet & Pulsa',
        ])->assertOk()->assertJsonPath('data.name', 'Internet & Pulsa');

        $this->deleteJson("/api/petty-cash-categories/{$id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function petugas_cannot_manage_categories(): void
    {
        $this->actingAsRole('petugas_bantuan');

        $this->postJson('/api/petty-cash-categories', [
            'code' => 'ATK',
            'name' => 'ATK',
        ])->assertForbidden();
    }

    #[Test]
    public function list_categories_with_active_only_does_not_query_missing_column(): void
    {
        $this->actingAsRole('petugas_bantuan');

        PettyCashCategory::query()->create([
            'code' => 'ATK',
            'name' => 'ATK',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PettyCashCategory::query()->create([
            'code' => 'OLD',
            'name' => 'Lama',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $this->getJson('/api/petty-cash-categories?active_only=1&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ATK');
    }

    #[Test]
    public function bendahara_creates_operational_float_for_existing_role_custodian(): void
    {
        $bendahara = $this->actingAsRole('bendahara');
        $pj = $this->makeUser('petugas_bantuan');

        $this->postJson('/api/petty-cash-floats', [
            'title' => 'Operasional Tim A — Oktober 2026',
            'purpose' => PettyCashFloatPurpose::OPERATIONAL->value,
            'custodian_user_id' => $pj->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.purpose', 'operational')
            ->assertJsonPath('data.custodian_user_id', $pj->id)
            ->assertJsonPath('data.created_by', $bendahara->id);

        $this->assertDatabaseHas('petty_cash_floats', [
            'custodian_user_id' => $pj->id,
            'status' => PettyCashFloatStatus::DRAFT->value,
        ]);
        $this->assertStringStartsWith('KSK/', PettyCashFloat::query()->firstOrFail()->float_number);
    }

    #[Test]
    public function operational_float_requires_period(): void
    {
        $this->actingAsRole('bendahara');
        $pj = $this->makeUser('verifikator');

        $this->postJson('/api/petty-cash-floats', [
            'title' => 'Tanpa periode',
            'purpose' => PettyCashFloatPurpose::OPERATIONAL->value,
            'custodian_user_id' => $pj->id,
        ])->assertUnprocessable();
    }

    #[Test]
    public function custodian_sees_only_own_floats_and_can_add_lines_when_reporting(): void
    {
        $pj = $this->makeUser('petugas_bantuan');
        $other = $this->makeUser('verifikator');
        $bendahara = $this->makeUser('bendahara');
        $category = PettyCashCategory::factory()->create(['code' => 'ATK', 'name' => 'ATK']);

        $mine = PettyCashFloat::factory()->reporting()->create([
            'custodian_user_id' => $pj->id,
            'created_by' => $bendahara->id,
            'title' => 'Float Saya',
        ]);
        PettyCashFloat::factory()->reporting()->create([
            'custodian_user_id' => $other->id,
            'created_by' => $bendahara->id,
            'title' => 'Float Orang Lain',
        ]);

        $this->actingAsRole('petugas_bantuan');
        // Re-auth as specific PJ
        Sanctum::actingAs($pj);

        $this->getJson('/api/petty-cash-floats')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Float Saya');

        $this->postJson("/api/petty-cash-floats/{$mine->id}/lines", [
            'category_id' => $category->id,
            'spent_on' => '2026-10-05',
            'amount' => '75000.00',
            'description' => 'Beli kertas',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '75000.00')
            ->assertJsonPath('data.category.code', 'ATK');

        $this->getJson("/api/petty-cash-floats/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.spent_total', '75000.00')
            ->assertJsonCount(1, 'data.lines');
    }

    #[Test]
    public function cannot_add_lines_while_draft(): void
    {
        $bendahara = $this->actingAsRole('bendahara');
        $pj = $this->makeUser('petugas_bantuan');
        $category = PettyCashCategory::factory()->create();

        $float = PettyCashFloat::factory()->create([
            'status' => PettyCashFloatStatus::DRAFT,
            'custodian_user_id' => $pj->id,
            'created_by' => $bendahara->id,
        ]);

        Sanctum::actingAs($pj);

        $this->postJson("/api/petty-cash-floats/{$float->id}/lines", [
            'category_id' => $category->id,
            'spent_on' => '2026-10-05',
            'amount' => '10000.00',
            'description' => 'Terlalu dini',
        ])->assertForbidden();
    }

    #[Test]
    public function inactive_category_rejected_on_line_create(): void
    {
        $bendahara = $this->makeUser('bendahara');
        $pj = $this->makeUser('petugas_bantuan');
        $category = PettyCashCategory::factory()->create(['is_active' => false]);

        $float = PettyCashFloat::factory()->reporting()->create([
            'custodian_user_id' => $pj->id,
            'created_by' => $bendahara->id,
        ]);

        Sanctum::actingAs($pj);

        $this->postJson("/api/petty-cash-floats/{$float->id}/lines", [
            'category_id' => $category->id,
            'spent_on' => '2026-10-05',
            'amount' => '10000.00',
            'description' => 'Kategori mati',
        ])->assertUnprocessable();
    }

    #[Test]
    public function donatur_has_no_petty_cash_access(): void
    {
        $this->actingAsRole('donatur');

        $this->getJson('/api/petty-cash-floats')->assertForbidden();
        $this->getJson('/api/petty-cash-categories')->assertForbidden();
    }
}
