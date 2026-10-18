<?php

namespace App\Http\Resources;

use App\Models\GrantApplicationComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GrantApplicationComment */
class GrantApplicationCommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewerId = (int) ($request->user()?->getKey() ?? 0);

        return [
            'id' => $this->id,
            'body' => $this->body,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'mentions' => $this->mentions
                ->map(fn ($mention) => $mention->user === null ? null : [
                    'id' => $mention->user->id,
                    'name' => $mention->user->name,
                ])
                ->filter()
                ->values(),
            'can_edit' => (int) $this->user_id === $viewerId,
            'can_delete' => (int) $this->user_id === $viewerId || (bool) $request->user()?->hasRole('admin'),
        ];
    }
}
