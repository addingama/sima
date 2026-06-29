<?php

namespace App\Domains\Expense\Services;

use App\Domains\Expense\DTOs\CreateExpenseDto;
use App\Domains\Expense\DTOs\ReverseExpenseDto;
use App\Domains\Expense\DTOs\UpdateExpenseDto;
use App\Domains\Expense\Events\ExpenseApproved;
use App\Domains\Expense\Events\ExpenseCreated;
use App\Domains\Expense\Events\ExpenseRejected;
use App\Domains\Expense\Events\ExpenseReversed;
use App\Domains\Expense\Events\ExpenseSubmitted;
use App\Domains\Expense\Events\ExpenseUpdated;
use App\Domains\Expense\Events\ExpenseVerified;
use App\Domains\Expense\Repositories\ExpenseRepository;
use App\Domains\Expense\Validators\ExpenseValidator;
use App\Domains\Ledger\Services\LedgerService;
use App\Enums\DisbursementStatus;
use App\Enums\LedgerMovement;
use App\Enums\TransactionType;
use App\Models\Disbursement;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryDto;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepository $repository,
        private readonly ExpenseValidator $validator,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function paginate(ListQueryDto $query): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function findForShow(Disbursement $disbursement): Disbursement
    {
        return $disbursement->load([
            'account:id,code,name',
            'program:id,code,name',
            'fundSources.fund:id,code,name',
            'fundSources.program:id,code,name',
            'approvals.actor:id,name',
            'attachments',
        ]);
    }

    public function loadWithSources(Disbursement $disbursement): Disbursement
    {
        return $disbursement->load('fundSources.fund', 'fundSources.program');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $sources
     */
    public function create(array $data, array $sources, User $actor): Disbursement
    {
        return $this->createFromDto(new CreateExpenseDto($data, $sources, $actor));
    }

    public function createFromDto(CreateExpenseDto $dto): Disbursement
    {
        $amount = bcadd((string) $dto->data['amount'], '0', 2);
        $normalized = $this->normalizeSources($dto->sources);
        $this->validator->assertSourcesMatch($amount, $normalized);

        return DB::transaction(function () use ($dto, $amount, $normalized): Disbursement {
            $expense = $this->repository->create([
                ...$dto->data,
                'amount' => $amount,
                'disbursement_number' => $dto->data['disbursement_number'] ?? $this->numbers->next('DSB'),
                'status' => DisbursementStatus::DRAFT->value,
                'created_by' => $dto->actor->getKey(),
            ]);

            $this->repository->syncSources($expense, $normalized);
            event(new ExpenseCreated($expense, $dto->actor));

            return $expense->load('fundSources');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $sources
     */
    public function update(Disbursement $expense, array $data, ?array $sources, User $actor): Disbursement
    {
        return $this->updateFromDto(new UpdateExpenseDto($data, $sources, $actor), $expense);
    }

    public function updateFromDto(UpdateExpenseDto $dto, Disbursement $expense): Disbursement
    {
        $this->validator->assertStatus($expense, [DisbursementStatus::DRAFT]);

        return DB::transaction(function () use ($dto, $expense): Disbursement {
            $before = $expense->toArray();
            $this->repository->update($expense, $dto->data);

            if ($dto->sources !== null) {
                $normalized = $this->normalizeSources($dto->sources);
                $this->validator->assertSourcesMatch((string) $expense->amount, $normalized);
                $this->repository->deleteSources($expense);
                $this->repository->syncSources($expense, $normalized);
            }

            event(new ExpenseUpdated($expense->refresh(), $before, $dto->actor));

            return $expense->refresh()->load('fundSources');
        });
    }

    public function submit(Disbursement $expense, User $actor): Disbursement
    {
        $this->validator->assertStatus($expense, [DisbursementStatus::DRAFT]);
        $this->validator->assertFundsAvailable($expense);

        return DB::transaction(function () use ($expense, $actor): Disbursement {
            $this->repository->markSubmitted($expense, $actor);
            event(new ExpenseSubmitted($expense->refresh(), $actor));

            return $expense->refresh();
        });
    }

    public function verify(Disbursement $expense, User $actor, ?string $notes = null): Disbursement
    {
        $this->validator->assertStatus($expense, [DisbursementStatus::SUBMITTED]);
        $this->validator->assertFundsAvailable($expense);

        return DB::transaction(function () use ($expense, $actor, $notes): Disbursement {
            $this->repository->markVerified($expense, $actor);
            event(new ExpenseVerified($expense->refresh(), $actor, $notes));

            return $expense->refresh();
        });
    }

    public function approve(Disbursement $expense, User $actor, ?string $notes = null): Disbursement
    {
        $this->validator->assertStatus($expense, [DisbursementStatus::VERIFIED]);
        $this->validator->assertFundsAvailable($expense);

        return DB::transaction(function () use ($expense, $actor, $notes): Disbursement {
            $fundLines = $expense->fundSources->map(fn ($source) => [
                'fund_id' => $source->fund_id,
                'amount' => bcadd((string) $source->amount, '0', 2),
            ])->all();

            $this->ledger->postAmanahMovement(
                TransactionType::EXPENSE,
                $expense->id,
                $expense->account_id,
                $fundLines,
                LedgerMovement::OUT,
                'Pengeluaran '.$expense->disbursement_number,
            );

            $this->repository->markApproved($expense, $actor);
            event(new ExpenseApproved($expense->refresh(), $actor, $notes));

            return $expense->refresh();
        });
    }

    public function reject(Disbursement $expense, User $actor, string $reason): Disbursement
    {
        $this->validator->assertStatus($expense, [DisbursementStatus::SUBMITTED, DisbursementStatus::VERIFIED]);

        return DB::transaction(function () use ($expense, $actor, $reason): Disbursement {
            $this->repository->markRejected($expense, $actor, $reason);
            event(new ExpenseRejected($expense->refresh(), $actor, $reason));

            return $expense->refresh();
        });
    }

    /** @param array<int, mixed> $sources */
    private function normalizeSources(array $sources): array
    {
        return array_map(function ($s) {
            if ($s instanceof \App\Domains\Expense\DTOs\ExpenseFundSourceDto) {
                return $s->toArray();
            }

            return $s;
        }, $sources);
    }
}

class ExpenseReversalService
{
    public function __construct(
        private readonly ExpenseRepository $repository,
        private readonly ExpenseValidator $validator,
        private readonly LedgerService $ledger,
    ) {}

    public function reverse(Disbursement $expense, User $actor, string $reason): Disbursement
    {
        return $this->reverseFromDto(new ReverseExpenseDto($expense, $actor, $reason));
    }

    public function reverseFromDto(ReverseExpenseDto $dto): Disbursement
    {
        $this->validator->assertApprovedForReversal($dto->expense);

        return DB::transaction(function () use ($dto): Disbursement {
            $this->ledger->reverse(
                TransactionType::EXPENSE,
                $dto->expense->id,
                $dto->expense->id,
                'Reversal pengeluaran: '.$dto->reason,
            );

            $this->repository->markReversed($dto->expense, $dto->actor, $dto->reason);
            event(new ExpenseReversed($dto->expense->refresh(), $dto->actor, $dto->reason));

            return $dto->expense->refresh();
        });
    }
}
