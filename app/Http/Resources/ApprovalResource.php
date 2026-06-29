<?php

namespace App\Http\Resources;

use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Approval */
class ApprovalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action?->value ?? $this->action,
            'actor' => $this->whenLoaded('actor', fn () => [
                'id' => $this->actor?->id,
                'name' => $this->actor?->name,
            ]),
            'actor_role' => $this->actor_role,
            'notes' => $this->notes,
            'acted_at' => $this->acted_at?->toIso8601String(),
        ];
    }
}
