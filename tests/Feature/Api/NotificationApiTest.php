<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function inbox_lists_read_and_unread_with_filter_and_pagination(): void
    {
        $user = $this->actingAsRole('verifikator');
        $older = $this->notify($user, ['excerpt' => 'Lama'], now()->subHour());
        $unread = $this->notify($user, ['excerpt' => 'Baru']);
        $read = $this->notify($user, ['excerpt' => 'Sudah'], now()->subMinute(), true);

        $this->getJson('/api/notifications?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $unread)
            ->assertJsonPath('meta.unread_count', 2)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2);

        $this->getJson('/api/notifications?page=2&per_page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $older);

        $this->getJson('/api/notifications?status=unread')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 2);

        $this->getJson('/api/notifications?status=read')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $read);

        $this->getJson('/api/notifications?status=archived')->assertUnprocessable();
    }

    #[Test]
    public function user_can_mark_own_notification_unread_and_cannot_touch_another_users(): void
    {
        $owner = $this->actingAsRole('verifikator');
        $id = $this->notify($owner, ['excerpt' => 'Milik verifikator']);
        $owner->notifications()->whereKey($id)->first()?->markAsRead();

        $this->postJson("/api/notifications/{$id}/unread")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.read_at', null);
        $this->getJson('/api/notifications')->assertJsonPath('meta.unread_count', 1);

        $other = $this->makeUser('petugas_bantuan');
        $foreign = $this->notify($other, ['excerpt' => 'Bukan milikmu']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/notifications/{$foreign}/read")->assertNotFound();
        $this->postJson("/api/notifications/{$foreign}/unread")->assertNotFound();
        $this->deleteJson("/api/notifications/{$foreign}")->assertNotFound();
        $this->assertNotNull($other->notifications()->whereKey($foreign)->first());
    }

    #[Test]
    public function user_can_delete_one_notification_and_all_read_ones(): void
    {
        $user = $this->actingAsRole('petugas_bantuan');
        $keep = $this->notify($user, ['excerpt' => 'Tetap']);
        $drop = $this->notify($user, ['excerpt' => 'Hapus satu']);
        $read = $this->notify($user, ['excerpt' => 'Sudah dibaca'], read: true);

        $this->deleteJson("/api/notifications/{$drop}")
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);
        $this->assertNull($user->notifications()->whereKey($drop)->first());

        $this->deleteJson('/api/notifications/read')
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertEqualsCanonicalizing(
            [$keep],
            $user->notifications()->pluck('id')->all(),
        );
        $this->getJson('/api/notifications')->assertJsonPath('meta.unread_count', 1);
    }

    /** @param  array<string, mixed>  $data */
    private function notify(User $user, array $data = [], ?\DateTimeInterface $createdAt = null, bool $read = false): string
    {
        $stamp = $createdAt ?? now();
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\GrantApplicationCommentNotification',
            'data' => array_merge([
                'kind' => 'grant_comment',
                'grant_application_id' => 12,
                'application_number' => 'BNT/2026/000012',
                'comment_id' => 3,
                'actor_name' => 'Siti',
                'excerpt' => 'Mohon dicek',
            ], $data),
            'read_at' => $read ? $stamp : null,
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ]);

        return $id;
    }
}
