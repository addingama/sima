<?php

namespace App\Domains\Transfer\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Transfer\Validators\TransferValidator;
use App\Enums\AccountTransferStatus;
use App\Enums\LedgerAccountType;
use App\Enums\TransactionType;
use App\Models\AccountTransfer;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
        private readonly TransferValidator $validator,
        private readonly AuditLogService $audit,
    ) {}

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            AccountTransfer::query()->with([
                'fromAccount:id,code,name',
                'toAccount:id,code,name',
            ]),
            $query,
            searchColumns: ['transfer_number', 'reference_number', 'description'],
            sortable: ['transfer_date', 'transfer_number', 'amount', 'created_at'],
            defaultSort: 'transfer_date',
            filterCallbacks: [
                'from' => fn ($q, $v) => $q->whereDate('transfer_date', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('transfer_date', '<=', $v),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function findForShow(AccountTransfer $transfer): AccountTransfer
    {
        return $transfer->load([
            'fromAccount:id,code,name',
            'toAccount:id,code,name',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): AccountTransfer
    {
        $fromId = (int) $data['from_account_id'];
        $toId = (int) $data['to_account_id'];
        $this->validator->assertAccountsValid($fromId, $toId);

        return DB::transaction(function () use ($data, $actor): AccountTransfer {
            $transfer = AccountTransfer::create([
                ...$data,
                'amount' => bcadd((string) $data['amount'], '0', 2),
                'transfer_number' => $data['transfer_number'] ?? $this->numbers->next('TRF'),
                'status' => AccountTransferStatus::DRAFT->value,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log($transfer, 'created', null, $transfer->toArray(), $actor);

            return $transfer;
        });
    }

    public function post(AccountTransfer $transfer, User $actor): AccountTransfer
    {
        $this->validator->assertDraft($transfer);
        $this->validator->assertAccountsValid($transfer->from_account_id, $transfer->to_account_id);

        $amount = bcadd((string) $transfer->amount, '0', 2);

        return DB::transaction(function () use ($transfer, $actor, $amount): AccountTransfer {
            $before = $transfer->toArray();

            $this->ledger->postJournal(
                TransactionType::TRANSFER,
                $transfer->id,
                [
                    [
                        'ledger_account_type' => LedgerAccountType::ACCOUNT,
                        'ledger_account_id' => $transfer->from_account_id,
                        'debit' => '0.00',
                        'credit' => $amount,
                    ],
                    [
                        'ledger_account_type' => LedgerAccountType::ACCOUNT,
                        'ledger_account_id' => $transfer->to_account_id,
                        'debit' => $amount,
                        'credit' => '0.00',
                    ],
                ],
                'Transfer '.$transfer->transfer_number,
            );

            $transfer->update([
                'status' => AccountTransferStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor->getKey(),
            ]);

            $this->audit->log($transfer, 'posted', $before, $transfer->fresh()->toArray(), $actor);

            return $transfer->refresh();
        });
    }

    public function reverse(AccountTransfer $transfer, User $actor, string $reason): AccountTransfer
    {
        $this->validator->assertPostedForReversal($transfer);

        return DB::transaction(function () use ($transfer, $actor, $reason): AccountTransfer {
            $before = $transfer->toArray();

            $this->ledger->reverse(
                TransactionType::TRANSFER,
                $transfer->id,
                $transfer->id,
                'Reversal transfer: '.$reason,
            );

            $transfer->update([
                'status' => AccountTransferStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);

            $this->audit->log($transfer, 'reversed', $before, $transfer->fresh()->toArray(), $actor, $reason);

            return $transfer->refresh();
        });
    }
}
