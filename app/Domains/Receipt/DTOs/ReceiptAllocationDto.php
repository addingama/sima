<?php

namespace App\Domains\Receipt\DTOs;

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
