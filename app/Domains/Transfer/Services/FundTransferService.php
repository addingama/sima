<?php

namespace App\Domains\Transfer\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Transfer\Validators\FundTransferValidator;
use App\Enums\AccountTransferStatus;
use App\Enums\LedgerAccountType;
use App\Enums\TransactionType;
use App\Models\FundTransfer;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FundTransferService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
        private readonly FundTransferValidator $validator,
        private readonly AuditLogService $audit,
    ) {}

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            FundTransfer::query()->with([
                'fromFund:id,code,name',
                'toFund:id,code,name',
            ]),
            $query,
            searchColumns: ['transfer_number', 'reference_number', 'description'],
            sortable: ['transfer_date', 'transfer_number', 'amount', 'created_at'],
            defaultSort: 'transfer_date',
            filterCallbacks: [
                'status' => fn ($q, $v) => $q->where('status', $v),
                'from_fund_id' => fn ($q, $v) => $q->where('from_fund_id', $v),
                'to_fund_id' => fn ($q, $v) => $q->where('to_fund_id', $v),
                'from' => fn ($q, $v) => $q->whereDate('transfer_date', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('transfer_date', '<=', $v),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function findForShow(FundTransfer $transfer): FundTransfer
    {
        return $transfer->load([
            'fromFund:id,code,name,type',
            'toFund:id,code,name,type',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): FundTransfer
    {
        $fromId = (int) $data['from_fund_id'];
        $toId = (int) $data['to_fund_id'];
        $this->validator->assertFundsValid($fromId, $toId);

        return DB::transaction(function () use ($data, $actor): FundTransfer {
            $transfer = FundTransfer::create([
                ...$data,
                'amount' => bcadd((string) $data['amount'], '0', 2),
                'transfer_number' => $data['transfer_number'] ?? $this->numbers->next('FTF'),
                'status' => AccountTransferStatus::DRAFT->value,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log($transfer, 'created', null, $transfer->toArray(), $actor);

            return $transfer;
        });
    }

    public function post(FundTransfer $transfer, User $actor): FundTransfer
    {
        $this->validator->assertDraft($transfer);
        $this->validator->assertFundsValid($transfer->from_fund_id, $transfer->to_fund_id);

        $amount = bcadd((string) $transfer->amount, '0', 2);

        return DB::transaction(function () use ($transfer, $actor, $amount): FundTransfer {
            $before = $transfer->toArray();

            $this->ledger->postJournal(
                TransactionType::FUND_TRANSFER,
                $transfer->id,
                [
                    [
                        'ledger_account_type' => LedgerAccountType::FUND,
                        'ledger_account_id' => $transfer->from_fund_id,
                        'debit' => $amount,
                        'credit' => '0.00',
                    ],
                    [
                        'ledger_account_type' => LedgerAccountType::FUND,
                        'ledger_account_id' => $transfer->to_fund_id,
                        'debit' => '0.00',
                        'credit' => $amount,
                    ],
                ],
                'Transfer Dana Amanah '.$transfer->transfer_number,
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

    public function reverse(FundTransfer $transfer, User $actor, string $reason): FundTransfer
    {
        $this->validator->assertPostedForReversal($transfer);

        return DB::transaction(function () use ($transfer, $actor, $reason): FundTransfer {
            $before = $transfer->toArray();

            $this->ledger->reverse(
                TransactionType::FUND_TRANSFER,
                $transfer->id,
                $transfer->id,
                'Reversal transfer Dana Amanah: '.$reason,
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
