<?php

namespace Tests\Feature\Api;

use App\Enums\GrantApplicationStatus;
use App\Enums\GrantBeneficiaryType;
use App\Models\GrantApplication;
use App\Models\GrantApplicationAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrantApplicationCommentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function mention_grants_view_but_not_update(): void
    {
        $creator = $this->actingAsRole('petugas_bantuan');
        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $other = $this->actingAsRole('petugas_bantuan');
        $this->getJson("/api/grant-applications/{$id}")->assertForbidden();
        $this->postJson("/api/grant-applications/{$id}/comments", [
            'body' => 'Tidak boleh',
        ])->assertForbidden();

        Sanctum::actingAs($creator);
        $this->postJson("/api/grant-applications/{$id}/comments", [
            'body' => "Mohon dicek @[user:{$other->id}]",
        ])->assertCreated()
            ->assertJsonPath('data.mentions.0.id', $other->id)
            ->assertJsonPath('data.mentions.0.name', $other->name);

        Sanctum::actingAs($other);
        $this->getJson("/api/grant-applications/{$id}")
            ->assertOk()
            ->assertJsonPath('data.comments_count', 1)
            ->assertJsonPath('data.mention_access.0.id', $other->id);
        $this->putJson("/api/grant-applications/{$id}", [
            'reason' => 'Tidak boleh diubah hanya karena disebut',
        ])->assertForbidden();
        $this->getJson('/api/grant-applications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.comments_count', 1);

        $this->assertSame(1, GrantApplicationAccess::query()->where('grant_application_id', $id)->where('user_id', $other->id)->count());
    }

    #[Test]
    public function donor_and_inactive_user_cannot_be_mentioned(): void
    {
        $this->actingAsRole('petugas_bantuan');
        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $donor = $this->makeUser('donatur');
        $inactive = $this->makeUser('verifikator');
        $inactive->update(['is_active' => false]);

        $this->postJson("/api/grant-applications/{$id}/comments", [
            'body' => "Panggil @[user:{$donor->id}]",
        ])->assertStatus(422);

        $this->postJson("/api/grant-applications/{$id}/comments", [
            'body' => "Panggil @[user:{$inactive->id}]",
        ])->assertStatus(422);

        $this->getJson("/api/grant-applications/{$id}/comments")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $names = collect($this->getJson('/api/grant-applications/mentionable-users')->assertOk()->json('data'))
            ->pluck('id');
        $this->assertFalse($names->contains($donor->id));
        $this->assertFalse($names->contains($inactive->id));
    }

    #[Test]
    public function comment_notifies_participants_and_marks_mentions(): void
    {
        [$id, $creator, $verifier, $handover, $stranger, $ketua] = $this->cardWithDistinctParticipants();

        Sanctum::actingAs($verifier);
        $this->postJson("/api/grant-applications/{$id}/comments", [
            'body' => "Serahkan ke @[user:{$handover->id}] dan @[user:{$stranger->id}] @[user:{$stranger->id}]",
        ])->assertCreated();

        $this->assertSame(1, GrantApplicationAccess::query()->where('user_id', $stranger->id)->count());
        $this->assertNotification($creator, 'grant_comment');
        $this->assertNotification($handover, 'grant_mention');
        $this->assertNotification($stranger, 'grant_mention');
        $this->assertNotification($verifier, null);
        $this->assertNotification($ketua, null);

        $this->assertSame(1, $handover->notifications()->count());
    }

    #[Test]
    public function edit_notifies_only_new_mentions_and_delete_keeps_access(): void
    {
        [$id, $creator, $verifier, $handover, $stranger, $ketua] = $this->cardWithDistinctParticipants();

        Sanctum::actingAs($verifier);
        $commentId = $this->postJson("/api/grant-applications/{$id}/comments", [
            'body' => "Cek @[user:{$stranger->id}]",
        ])->assertCreated()->json('data.id');

        $creator->notifications()->delete();
        $handover->notifications()->delete();
        $stranger->notifications()->delete();

        $edited = $this->putJson("/api/grant-applications/{$id}/comments/{$commentId}", [
            'body' => "Cek @[user:{$stranger->id}] dan @[user:{$ketua->id}]",
        ])->assertOk();
        $this->assertIsString($edited->json('data.edited_at'));

        $this->assertNotification($ketua, 'grant_mention');
        $this->assertNotification($stranger, null);
        $this->assertNotification($creator, null);
        $this->assertNotification($handover, null);

        Sanctum::actingAs($verifier);
        $this->deleteJson("/api/grant-applications/{$id}/comments/{$commentId}")
            ->assertOk();

        $this->getJson("/api/grant-applications/{$id}/comments")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($stranger);
        $this->getJson("/api/grant-applications/{$id}")->assertOk();
        $this->assertTrue(GrantApplicationAccess::query()->where('user_id', $stranger->id)->exists());
        $this->assertTrue(GrantApplicationAccess::query()->where('user_id', $ketua->id)->exists());
    }

    #[Test]
    public function rejected_card_still_accepts_a_comment_and_notifications_can_be_marked_read(): void
    {
        $creator = $this->actingAsRole('petugas_bantuan');
        $watcher = $this->makeUser('verifikator');
        $grant = GrantApplication::factory()->create([
            'status' => GrantApplicationStatus::REJECTED,
            'created_by' => $creator->id,
            'assigned_verifier_id' => $watcher->id,
        ]);

        Sanctum::actingAs($creator);
        $this->postJson("/api/grant-applications/{$grant->id}/comments", [
            'body' => 'Tetap bisa dikomentari',
        ])->assertCreated();

        Sanctum::actingAs($watcher);
        $listed = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.kind', 'grant_comment');
        $read = $this->postJson('/api/notifications/'.$listed->json('data.0.id').'/read')->assertOk();
        $this->assertIsString($read->json('data.read_at'));
        $this->getJson('/api/notifications')->assertJsonPath('meta.unread_count', 0);

        Sanctum::actingAs($creator);
        $this->postJson("/api/grant-applications/{$grant->id}/comments", [
            'body' => 'Catatan lanjutan',
        ])->assertCreated();

        Sanctum::actingAs($watcher);
        $this->postJson('/api/notifications/read-all', [
            'grant_application_id' => $grant->id,
        ])->assertOk()->assertJsonPath('data.marked', 1);
        $this->getJson('/api/notifications')->assertJsonPath('meta.unread_count', 0);
    }

    /** @return array{0: int, 1: User, 2: User, 3: User, 4: User, 5: User} */
    private function cardWithDistinctParticipants(): array
    {
        $creator = $this->actingAsRole('petugas_bantuan');
        $id = $this->postJson('/api/grant-applications', $this->draftPayload())
            ->assertCreated()
            ->json('data.id');

        $verifier = $this->makeUser('verifikator');
        $handover = $this->makeUser('petugas_bantuan');
        $stranger = $this->makeUser('petugas_bantuan');
        $ketua = $this->actingAsRole('ketua');
        $this->postJson("/api/grant-applications/{$id}/assign", [
            'assigned_verifier_id' => $verifier->id,
            'assigned_handover_id' => $handover->id,
        ])->assertOk();

        return [$id, $creator, $verifier, $handover, $stranger, $ketua];
    }

    private function assertNotification(User $user, ?string $kind): void
    {
        Sanctum::actingAs($user);
        $response = $this->getJson('/api/notifications')->assertOk();

        if ($kind === null) {
            $response->assertJsonPath('meta.unread_count', 0);

            return;
        }

        $response->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.kind', $kind);
    }

    /** @return array<string, mixed> */
    private function draftPayload(): array
    {
        return [
            'beneficiary_type' => GrantBeneficiaryType::INDIVIDUAL->value,
            'recipient_name' => 'Siti Aminah',
            'recommended_amount' => '250000.00',
            'reason' => 'Bantuan sembako',
            'recommender_name' => 'Pak RT',
        ];
    }
}
