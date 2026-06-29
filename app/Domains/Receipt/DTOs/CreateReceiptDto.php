<?php

namespace App\Domains\Receipt\DTOs;

use App\Models\User;

readonly class ReceiptAllocationDto
{
    public function __construct(
        public int $fundId,
        public string $amount,
        public ?int $programId = null,
        public ?string $note = null,
    ) {}

    /** @return array{fund_id:int, amount:string, program_id?:int|null, note?:string|null} */
    public function toArray(): array
    {
        return array_filter([
            'fund_id' => $this->fundId,
            'amount' => $this->amount,
            'program_id' => $this->programId,
            'note' => $this->note,
        ], fn ($v) => $v !== null);
    }
}

readonly class CreateReceiptDto
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, ReceiptAllocationDto|array<string, mixed>>  $allocations
     */
    public function __construct(
        public array $data,
        public array $allocations,
        public User $actor,
    ) {}
}

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

readonly class ReverseReceiptDto
{
    public function __construct(
        public \App\Models\Receipt $receipt,
        public User $actor,
        public string $reason,
    ) {}
}
