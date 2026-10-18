<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class InAppNotificationService
{
    /** @return Collection<int, DatabaseNotification> */
    public function listFor(User $user, int $limit = 30): Collection
    {
        return $user->notifications()->latest()->limit($limit)->get();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markRead(User $user, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return $notification->refresh();
    }

    public function markAllRead(User $user, ?int $grantApplicationId = null): int
    {
        $notifications = $user->unreadNotifications()->get();

        if ($grantApplicationId !== null) {
            $notifications = $notifications->filter(
                fn (DatabaseNotification $notification): bool => (int) ($notification->data['grant_application_id'] ?? 0) === $grantApplicationId,
            );
        }

        $notifications->each(fn (DatabaseNotification $notification) => $notification->markAsRead());

        return $notifications->count();
    }
}
