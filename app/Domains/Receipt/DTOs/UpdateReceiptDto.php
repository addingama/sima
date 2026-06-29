<?php

namespace App\Domains\Receipt\DTOs;

use App\Models\User;

readonly class UpdateReceiptDto
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, ReceiptAllocationDto|array<string, mixed>>|null  $allocations
     */
    public function __construct(
        public array $data,
        public ?array $allocations,
        public User $actor,
    ) {}
}
