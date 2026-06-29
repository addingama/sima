<?php

namespace App\Domains\Expense\DTOs;

use App\Models\User;

readonly class ExpenseFundSourceDto
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

readonly class CreateExpenseDto
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, ExpenseFundSourceDto|array<string, mixed>>  $sources
     */
    public function __construct(
        public array $data,
        public array $sources,
        public User $actor,
    ) {}
}

readonly class UpdateExpenseDto
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, ExpenseFundSourceDto|array<string, mixed>>|null  $sources
     */
    public function __construct(
        public array $data,
        public ?array $sources,
        public User $actor,
    ) {}
}

readonly class ReverseExpenseDto
{
    public function __construct(
        public \App\Models\Disbursement $expense,
        public User $actor,
        public string $reason,
    ) {}
}
