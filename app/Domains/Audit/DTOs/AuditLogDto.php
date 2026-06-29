<?php

namespace App\Domains\Audit\DTOs;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

readonly class AuditLogDto
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public Model $entity,
        public string $action,
        public ?array $before = null,
        public ?array $after = null,
        public ?User $actor = null,
        public ?string $tags = null,
    ) {}
}
