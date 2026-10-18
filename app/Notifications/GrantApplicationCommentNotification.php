<?php

namespace App\Notifications;

use App\Models\GrantApplication;
use App\Models\GrantApplicationComment;
use App\Models\User;
use Illuminate\Notifications\Notification;

class GrantApplicationCommentNotification extends Notification
{
    public function __construct(
        public readonly string $kind,
        public readonly GrantApplication $grant,
        public readonly GrantApplicationComment $comment,
        public readonly User $actor,
        public readonly string $excerpt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'grant_application_id' => $this->grant->getKey(),
            'application_number' => $this->grant->application_number,
            'comment_id' => $this->comment->getKey(),
            'actor_name' => $this->actor->name,
            'excerpt' => $this->excerpt,
        ];
    }
}
