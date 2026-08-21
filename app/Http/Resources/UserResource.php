<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'donor_id' => $this->when(
                $this->relationLoaded('donor'),
                fn () => $this->donor?->id
            ),
            'donor' => $this->when(
                $this->relationLoaded('donor') && $this->donor !== null,
                fn () => [
                    'id' => $this->donor->id,
                    'code' => $this->donor->code,
                    'name' => $this->donor->name,
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
