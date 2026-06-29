<?php

namespace App\Domains\Reconciliation\Services;

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Reconciliation\DTOs\AddReconciliationLineDto;
use App\Domains\Reconciliation\DTOs\CreateReconciliationDto;
use App\Domains\Reconciliation\Events\ReconciliationCompleted;
use App\Domains\Reconciliation\Events\ReconciliationCreated;
use App\Domains\Reconciliation\Repositories\BankReconciliationRepository;
use App\Domains\Reconciliation\Validators\ReconciliationValidator;
use App\Enums\BankFeeStatus;
use App\Models\BankFee;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function __construct(
        private readonly BankReconciliationRepository $repository,
        private readonly ReconciliationValidator $validator,
        private readonly LedgerService $ledger,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    public function systemBalanceAsOf(int $accountId, string $asOfDate): string
    {
        return $this->ledger->accountBalanceAsOf($accountId, $asOfDate);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): BankReconciliation
    {
        return $this->createFromDto(new CreateReconciliationDto($data, $actor));
    }

    public function createFromDto(CreateReconciliationDto $dto): BankReconciliation
    {
        return DB::transaction(function () use ($dto): BankReconciliation {
            $accountId = (int) $dto->data['account_id'];
            $periodEnd = (string) $dto->data['period_end'];
            $systemBalance = $this->systemBalanceAsOf($accountId, $periodEnd);
            $difference = bcsub(bcadd((string) $dto->data['statement_balance'], '0', 2), $systemBalance, 2);
            $reconciling = $this->deferredBankFeeItems($accountId, $periodEnd);
            $adjustedDifference = $this->adjustedDifference($difference, $reconciling['total']);

            $reconciliation = $this->repository->create([
                'account_id' => $accountId,
                'period_start' => $dto->data['period_start'],
                'period_end' => $periodEnd,
                'statement_balance' => $dto->data['statement_balance'],
                'system_balance' => $systemBalance,
                'difference' => $difference,
                'status' => 'draft',
                'notes' => $this->appendReconcilingNotes($dto->data['notes'] ?? null, $reconciling, $adjustedDifference),
                'created_by' => $dto->actor->getKey(),
            ]);

            $auditPayload = [
                ...$reconciliation->toArray(),
                'reconciling_items' => $reconciling['items'],
                'adjusted_difference' => $adjustedDifference,
            ];

            event(new ReconciliationCreated($reconciliation, $dto->actor, $auditPayload));

            return $reconciliation;
        });
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: string}
     */
    public function deferredBankFeeItems(int $accountId, string $asOfDate): array
    {
        $fees = BankFee::query()
            ->where('account_id', $accountId)
            ->where('status', BankFeeStatus::DEFERRED->value)
            ->whereDate('fee_date', '<=', $asOfDate)
            ->with('operationalLiability:id,liability_number,amount,status')
            ->get();

        $items = [];
        $total = '0.00';

        foreach ($fees as $fee) {
            $amount = bcadd((string) $fee->amount, '0', 2);
            $total = bcadd($total, $amount, 2);
            $items[] = [
                'type' => 'deferred_bank_fee',
                'bank_fee_id' => $fee->id,
                'fee_number' => $fee->fee_number,
                'fee_date' => $fee->fee_date->toDateString(),
                'amount' => $amount,
                'operational_liability_id' => $fee->operational_liability_id,
                'liability_number' => $fee->operationalLiability?->liability_number,
                'description' => 'Biaya bank tertunda — kas fisik sudah keluar, menunggu Dana Operasional',
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    public function adjustedDifference(string $rawDifference, string $reconcilingTotal): string
    {
        return bcadd($rawDifference, $reconcilingTotal, 2);
    }

    /** @param array<string, mixed> $data */
    public function addLine(BankReconciliation $reconciliation, array $data): BankReconciliationLine
    {
        $this->validator->assertDraft($reconciliation);

        return $this->repository->addLine($reconciliation, $data);
    }

    public function addLineFromDto(AddReconciliationLineDto $dto): BankReconciliationLine
    {
        return $this->addLine($dto->reconciliation, $dto->data);
    }

    public function complete(BankReconciliation $reconciliation, User $actor): BankReconciliation
    {
        $this->validator->assertDraft($reconciliation);

        return DB::transaction(function () use ($reconciliation, $actor): BankReconciliation {
            $periodEnd = $reconciliation->period_end->toDateString();
            $systemBalance = $this->systemBalanceAsOf($reconciliation->account_id, $periodEnd);
            $difference = bcsub((string) $reconciliation->statement_balance, $systemBalance, 2);
            $reconciling = $this->deferredBankFeeItems($reconciliation->account_id, $periodEnd);
            $adjustedDifference = $this->adjustedDifference($difference, $reconciling['total']);

            $this->repository->complete($reconciliation, [
                'system_balance' => $systemBalance,
                'difference' => $difference,
                'status' => 'completed',
                'reconciled_at' => now(),
                'reconciled_by' => $actor->getKey(),
                'notes' => $this->appendReconcilingNotes($reconciliation->notes, $reconciling, $adjustedDifference),
            ]);

            $auditPayload = [
                'system_balance' => $systemBalance,
                'difference' => $difference,
                'reconciling_items' => $reconciling['items'],
                'adjusted_difference' => $adjustedDifference,
            ];

            event(new ReconciliationCompleted($reconciliation->refresh(), $actor, $auditPayload));

            return $reconciliation->refresh();
        });
    }

    /** @param array{items: array<int, mixed>, total: string} $reconciling */
    private function appendReconcilingNotes(?string $notes, array $reconciling, string $adjustedDifference): ?string
    {
        if (bccomp($reconciling['total'], '0', 2) === 0) {
            return $notes;
        }

        $summary = sprintf(
            '[Reconciling] Biaya bank deferred: %s (%d item). Selisih disesuaikan: %s.',
            $reconciling['total'],
            count($reconciling['items']),
            $adjustedDifference
        );

        return $notes ? "{$notes}\n{$summary}" : $summary;
    }
}
