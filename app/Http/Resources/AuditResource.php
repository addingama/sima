<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OwenIt\Auditing\Models\Audit;

/** @mixin Audit */
class AuditResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'tags' => $this->tags,
            'url' => $this->url,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'user' => UserResource::make($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
