<?php

namespace App\Domains\Grant\Services;

use App\Exceptions\DomainException;
use App\Models\GrantApplication;
use App\Models\GrantApplicationAccess;
use App\Models\GrantApplicationComment;
use App\Models\User;
use App\Notifications\GrantApplicationCommentNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GrantApplicationCommentService
{
    private const MENTION_PATTERN = '/@\[user:(\d+)\]/';

    public function paginate(GrantApplication $grant, int $perPage, int $page): LengthAwarePaginator
    {
        return $grant->comments()
            ->with(['user:id,name', 'mentions.user:id,name'])
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function create(GrantApplication $grant, string $body, User $actor): GrantApplicationComment
    {
        return DB::transaction(function () use ($grant, $body, $actor): GrantApplicationComment {
            $mentioned = $this->resolveMentions($body);
            $comment = $grant->comments()->create([
                'user_id' => $actor->getKey(),
                'body' => $body,
            ]);
            $this->syncMentions($comment, $mentioned);
            $this->grantAccess($grant, $mentioned);
            $this->notifyNewComment($grant, $comment, $actor, $mentioned);

            return $this->load($comment);
        });
    }

    public function update(GrantApplication $grant, GrantApplicationComment $comment, string $body, User $actor): GrantApplicationComment
    {
        $this->assertOwnedBy($comment, $grant);

        if ((int) $comment->user_id !== (int) $actor->getKey()) {
            throw new AuthorizationException('Hanya penulis yang dapat mengubah komentar.');
        }

        return DB::transaction(function () use ($grant, $comment, $body, $actor): GrantApplicationComment {
            $previousIds = $comment->mentions()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $mentioned = $this->resolveMentions($body);
            $comment->update([
                'body' => $body,
                'edited_at' => now(),
            ]);
            $this->syncMentions($comment, $mentioned);
            $this->grantAccess($grant, $mentioned);

            $newIds = array_values(array_diff(
                $mentioned->keys()->map(fn ($id) => (int) $id)->all(),
                $previousIds,
            ));
            $this->notifyMentions($grant, $comment, $actor, $mentioned->only($newIds), $mentioned);

            return $this->load($comment);
        });
    }

    public function delete(GrantApplication $grant, GrantApplicationComment $comment, User $actor): void
    {
        $this->assertOwnedBy($comment, $grant);

        $isAuthor = (int) $comment->user_id === (int) $actor->getKey();
        if (! $isAuthor && ! $actor->hasRole('admin')) {
            throw new AuthorizationException('Hanya penulis atau admin yang dapat menghapus komentar.');
        }

        $comment->delete();
    }

    /** @return Collection<int, User> */
    private function resolveMentions(string $body): Collection
    {
        preg_match_all(self::MENTION_PATTERN, $body, $matches);
        $ids = array_values(array_unique(array_map('intval', $matches[1] ?? [])));

        if ($ids === []) {
            return collect();
        }

        $users = User::query()->whereIn('id', $ids)->get()->keyBy(fn (User $user) => (int) $user->getKey());

        if ($users->count() !== count($ids)) {
            throw new DomainException('Pengguna yang disebut harus akun internal yang aktif.');
        }

        foreach ($users as $user) {
            if (! $user->is_active || ! $user->can('grant.view')) {
                throw new DomainException('Pengguna yang disebut harus akun internal yang aktif.');
            }
        }

        return $users;
    }

    /** @param  Collection<int, User>  $mentioned */
    private function syncMentions(GrantApplicationComment $comment, Collection $mentioned): void
    {
        $comment->mentions()->delete();

        foreach ($mentioned as $user) {
            $comment->mentions()->create(['user_id' => $user->getKey()]);
        }
    }

    /** @param  Collection<int, User>  $mentioned */
    private function grantAccess(GrantApplication $grant, Collection $mentioned): void
    {
        foreach ($mentioned as $user) {
            GrantApplicationAccess::query()->firstOrCreate(
                [
                    'grant_application_id' => $grant->getKey(),
                    'user_id' => $user->getKey(),
                ],
                ['source' => GrantApplicationAccess::SOURCE_MENTION],
            );
        }
    }

    /** @param  Collection<int, User>  $mentioned */
    private function notifyNewComment(
        GrantApplication $grant,
        GrantApplicationComment $comment,
        User $actor,
        Collection $mentioned,
    ): void {
        $mentionIds = $mentioned->keys()->map(fn ($id) => (int) $id)->all();
        $participantIds = collect([
            $grant->created_by,
            $grant->assigned_verifier_id,
            $grant->assigned_handover_id,
        ])->filter()->map(fn ($id) => (int) $id);

        $recipientIds = $participantIds
            ->merge($mentionIds)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $actor->getKey())
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();
        $excerpt = $this->excerpt($comment->body, $mentioned);

        foreach ($recipients as $recipient) {
            $kind = in_array((int) $recipient->getKey(), $mentionIds, true)
                ? 'grant_mention'
                : 'grant_comment';

            $recipient->notify(new GrantApplicationCommentNotification(
                $kind,
                $grant,
                $comment,
                $actor,
                $excerpt,
            ));
        }
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  Collection<int, User>  $mentioned
     */
    private function notifyMentions(
        GrantApplication $grant,
        GrantApplicationComment $comment,
        User $actor,
        Collection $recipients,
        Collection $mentioned,
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        $excerpt = $this->excerpt($comment->body, $mentioned);

        foreach ($recipients as $recipient) {
            if ((int) $recipient->getKey() === (int) $actor->getKey()) {
                continue;
            }

            $recipient->notify(new GrantApplicationCommentNotification(
                'grant_mention',
                $grant,
                $comment,
                $actor,
                $excerpt,
            ));
        }
    }

    /** @param  Collection<int, User>  $mentioned */
    private function excerpt(string $body, Collection $mentioned): string
    {
        $text = preg_replace_callback(
            self::MENTION_PATTERN,
            function (array $match) use ($mentioned): string {
                $user = $mentioned->get((int) $match[1]);

                return '@'.($user instanceof User ? $user->name : 'pengguna');
            },
            $body,
        ) ?? $body;

        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return Str::limit($text, 140);
    }

    private function assertOwnedBy(GrantApplicationComment $comment, GrantApplication $grant): void
    {
        if ((int) $comment->grant_application_id !== (int) $grant->getKey()) {
            abort(404);
        }
    }

    private function load(GrantApplicationComment $comment): GrantApplicationComment
    {
        return $comment->load(['user:id,name', 'mentions.user:id,name']);
    }
}
