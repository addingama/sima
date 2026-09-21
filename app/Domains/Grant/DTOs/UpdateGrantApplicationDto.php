<?php

namespace App\Domains\Grant\DTOs;

use App\Models\User;

readonly class UpdateGrantApplicationDto
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public array $data,
        public User $actor,
    ) {}
}
