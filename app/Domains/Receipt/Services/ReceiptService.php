<?php

namespace App\Domains\Receipt\Services;

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Receipt\DTOs\CreateReceiptDto;
use App\Domains\Receipt\DTOs\ReceiptAllocationDto;
use App\Domains\Receipt\DTOs\ReverseReceiptDto;
use App\Domains\Receipt\DTOs\UpdateReceiptDto;
use App\Domains\Receipt\Events\ReceiptApproved;
use App\Domains\Receipt\Events\ReceiptCreated;
use App\Domains\Receipt\Events\ReceiptRejected;
use App\Domains\Receipt\Events\ReceiptReversed;
use App\Domains\Receipt\Events\ReceiptSubmitted;
use App\Domains\Receipt\Events\ReceiptUpdated;
use App\Domains\Receipt\Repositories\ReceiptRepository;
use App\Domains\Receipt\Validators\ReceiptValidator;
use App\Enums\LedgerMovement;
use App\Enums\ReceiptStatus;
use App\Enums\TransactionType;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function __construct(
        private readonly ReceiptRepository $repository,
        private readonly ReceiptValidator $validator,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function findForShow(Receipt $receipt): Receipt
    {
        return $receipt->load([
            'account:id,code,name',
            'donor:id,code,name',
            'allocations.fund:id,code,name',
            'allocations.program:id,code,name',
            'approvals.actor:id,name',
            'attachments',
        ]);
    }

    /** @return Collection<int, ReceiptAllocation> */
    public function allocationsFor(Receipt $receipt): Collection
    {
        return $receipt->allocations()->with(['fund:id,code,name', 'program:id,code,name'])->get();
    }

    public function loadWithAllocations(Receipt $receipt): Receipt
    {
        return $receipt->load('allocations.fund', 'allocations.program');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $allocations
     */
    public function create(array $data, array $allocations, User $actor): Receipt
    {
        return $this->createFromDto(new CreateReceiptDto($data, $allocations, $actor));
    }

    public function createFromDto(CreateReceiptDto $dto): Receipt
    {
        $this->validator->assertAllocationsMatch((string) $dto->data['amount'], $this->normalizeAllocations($dto->allocations));

        return DB::transaction(function () use ($dto): Receipt {
            $receipt = $this->repository->create([
                ...$dto->data,
                'amount' => bcadd((string) $dto->data['amount'], '0', 2),
                'receipt_number' => $dto->data['receipt_number'] ?? $this->numbers->next('RCP'),
                'status' => ReceiptStatus::DRAFT->value,
                'created_by' => $dto->actor->getKey(),
            ]);

            $this->repository->syncAllocations($receipt, $this->normalizeAllocations($dto->allocations), $dto->actor);
            event(new ReceiptCreated($receipt, $dto->actor));

            return $receipt->load('allocations');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $allocations
     */
    public function update(Receipt $receipt, array $data, ?array $allocations, User $actor): Receipt
    {
        return $this->updateFromDto(new UpdateReceiptDto($data, $allocations, $actor), $receipt);
    }

    public function updateFromDto(UpdateReceiptDto $dto, Receipt $receipt): Receipt
    {
        $this->validator->assertStatus($receipt, [ReceiptStatus::DRAFT]);

        return DB::transaction(function () use ($dto, $receipt): Receipt {
            $before = $receipt->toArray();
            $this->repository->update($receipt, $dto->data);

            if ($dto->allocations !== null) {
                $normalized = $this->normalizeAllocations($dto->allocations);
                $this->validator->assertAllocationsMatch((string) $receipt->amount, $normalized);
                $this->repository->deleteAllocations($receipt);
                $this->repository->syncAllocations($receipt, $normalized, $dto->actor);
            }

            event(new ReceiptUpdated($receipt->refresh(), $before, $dto->actor));

            return $receipt->refresh()->load('allocations');
        });
    }

    public function submit(Receipt $receipt, User $actor): Receipt
    {
        $this->validator->assertStatus($receipt, [ReceiptStatus::DRAFT]);
        $this->validator->assertHasAllocations($receipt);
        $this->validator->assertAllocationsMatchExisting($receipt);

        return DB::transaction(function () use ($receipt, $actor): Receipt {
            $this->repository->markSubmitted($receipt, $actor);
            event(new ReceiptSubmitted($receipt->refresh(), $actor));

            return $receipt->refresh();
        });
    }

    public function approve(Receipt $receipt, User $actor, ?string $notes = null): Receipt
    {
        $this->validator->assertStatus($receipt, [ReceiptStatus::SUBMITTED]);
        $this->validator->assertAllocationsMatchExisting($receipt);

        return DB::transaction(function () use ($receipt, $actor, $notes): Receipt {
            $fundLines = $receipt->allocations->map(fn ($alloc) => [
                'fund_id' => $alloc->fund_id,
                'amount' => bcadd((string) $alloc->amount, '0', 2),
            ])->all();

            $this->ledger->postAmanahMovement(
                TransactionType::RECEIPT,
                $receipt->id,
                $receipt->account_id,
                $fundLines,
                LedgerMovement::IN,
                'Penerimaan '.$receipt->receipt_number,
            );

            $this->repository->markApproved($receipt, $actor);
            event(new ReceiptApproved($receipt->refresh(), $actor, $notes));

            return $receipt->refresh();
        });
    }

    public function reject(Receipt $receipt, User $actor, string $reason): Receipt
    {
        $this->validator->assertStatus($receipt, [ReceiptStatus::SUBMITTED]);

        return DB::transaction(function () use ($receipt, $actor, $reason): Receipt {
            $this->repository->markRejected($receipt, $actor, $reason);
            event(new ReceiptRejected($receipt->refresh(), $actor, $reason));

            return $receipt->refresh();
        });
    }

    /** @param array<int, mixed> $allocations */
    private function normalizeAllocations(array $allocations): array
    {
        return array_map(function ($a) {
            if ($a instanceof ReceiptAllocationDto) {
                return $a->toArray();
            }

            return $a;
        }, $allocations);
    }
}

class ReceiptReversalService
{
    public function __construct(
        private readonly ReceiptRepository $repository,
        private readonly ReceiptValidator $validator,
        private readonly LedgerService $ledger,
    ) {}

    public function reverse(Receipt $receipt, User $actor, string $reason): Receipt
    {
        return $this->reverseFromDto(new ReverseReceiptDto($receipt, $actor, $reason));
    }

    public function reverseFromDto(ReverseReceiptDto $dto): Receipt
    {
        $this->validator->assertApprovedForReversal($dto->receipt);

        return DB::transaction(function () use ($dto): Receipt {
            $this->ledger->reverse(
                TransactionType::RECEIPT,
                $dto->receipt->id,
                $dto->receipt->id,
                'Reversal penerimaan: '.$dto->reason,
            );

            $this->repository->markReversed($dto->receipt, $dto->actor, $dto->reason);
            event(new ReceiptReversed($dto->receipt->refresh(), $dto->actor, $dto->reason));

            return $dto->receipt->refresh();
        });
    }
}
