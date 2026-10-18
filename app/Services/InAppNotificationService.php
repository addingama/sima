<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class InAppNotificationService
{
    /** @return LengthAwarePaginator<int, DatabaseNotification> */
    public function paginateFor(User $user, string $status = 'all', int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $query = $user->notifications()->latest();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markRead(User $user, string $id): DatabaseNotification
    {
        $notification = $this->owned($user, $id);
        $notification->markAsRead();

        return $notification->refresh();
    }

    public function markUnread(User $user, string $id): DatabaseNotification
    {
        $notification = $this->owned($user, $id);
        $notification->markAsUnread();

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

    public function delete(User $user, string $id): void
    {
        $this->owned($user, $id)->delete();
    }

    public function deleteRead(User $user): int
    {
        return $user->notifications()->whereNotNull('read_at')->delete();
    }

    private function owned(User $user, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($id)->firstOrFail();

        return $notification;
    }
}
