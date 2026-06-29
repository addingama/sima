<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\DTOs\AmanahMovementDto;
use App\Domains\Ledger\DTOs\ReverseJournalDto;
use App\Domains\Ledger\Events\LedgerJournalPosted;
use App\Domains\Ledger\Events\LedgerReversed;
use App\Domains\Ledger\Repositories\LedgerEntryRepository;
use App\Domains\Ledger\Validators\JournalValidator;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerMovement;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\BankFee;
use App\Models\Disbursement;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Amanah Ledger — mesin akuntansi inti (double-entry, ACID).
 * Domain ini TIDAK mengandung aturan bisnis penerimaan/pengeluaran.
 */
class LedgerService
{
    public function __construct(
        private readonly LedgerEntryRepository $entries,
        private readonly JournalValidator $validator,
    ) {}

    public function balanceForAccount(int $accountId): string
    {
        return $this->balanceFor(LedgerAccountType::ACCOUNT, $accountId);
    }

    public function balanceForFund(int $fundId): string
    {
        return $this->balanceFor(LedgerAccountType::FUND, $fundId);
    }

    public function balanceFor(LedgerAccountType $type, int $id): string
    {
        return $this->normalize($this->entries->rawBalanceFor($type, $id));
    }

    public function accountBalanceAsOf(int $accountId, string $asOfDate): string
    {
        return $this->normalize($this->entries->rawBalanceFor(
            LedgerAccountType::ACCOUNT,
            $accountId,
            $asOfDate
        ));
    }

    /**
     * @param  array<int, array{ledger_account_type: LedgerAccountType|string, ledger_account_id: int, debit: string|float, credit: string|float}>  $lines
     * @return Collection<int, LedgerEntry>
     */
    public function postJournal(
        TransactionType $transactionType,
        int $transactionId,
        array $lines,
        ?string $reference = null,
    ): Collection {
        $normalized = $this->validator->normalizeLines($lines);
        $this->validator->assertJournalBalanced($normalized);

        return DB::transaction(function () use ($transactionType, $transactionId, $normalized, $reference): Collection {
            $this->lockLedgerAccounts($normalized);
            $this->assertSufficientForOutflows($normalized);

            $now = now();
            $created = collect();

            foreach ($normalized as $line) {
                $created->push($this->entries->create([
                    'transaction_type' => $transactionType->value,
                    'transaction_id' => $transactionId,
                    'ledger_account_type' => $line['ledger_account_type']->value,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'reference' => $reference,
                    'created_at' => $now,
                ]));
            }

            event(new LedgerJournalPosted($transactionType, $transactionId, $created));

            return $created;
        });
    }

    /**
     * @param  array<int, array{fund_id: int, amount: string|float}>  $fundLines
     * @return Collection<int, LedgerEntry>
     */
    public function postAmanahMovement(
        TransactionType $transactionType,
        int $transactionId,
        int $accountId,
        array $fundLines,
        LedgerMovement $movement,
        ?string $reference = null,
    ): Collection {
        return $this->postAmanahMovementDto(new AmanahMovementDto(
            $transactionType,
            $transactionId,
            $accountId,
            $fundLines,
            $movement,
            $reference,
        ));
    }

    /** @return Collection<int, LedgerEntry> */
    public function postAmanahMovementDto(AmanahMovementDto $dto): Collection
    {
        $lines = [];

        foreach ($dto->fundLines as $row) {
            $amount = bcadd((string) $row['amount'], '0', 2);
            $fundId = (int) $row['fund_id'];

            if ($dto->movement === LedgerMovement::IN) {
                $lines[] = $this->line(LedgerAccountType::ACCOUNT, $dto->accountId, $amount, '0');
                $lines[] = $this->line(LedgerAccountType::FUND, $fundId, '0', $amount);
            } else {
                $lines[] = $this->line(LedgerAccountType::ACCOUNT, $dto->accountId, '0', $amount);
                $lines[] = $this->line(LedgerAccountType::FUND, $fundId, $amount, '0');
            }
        }

        return $this->postJournal(
            $dto->transactionType,
            $dto->transactionId,
            $lines,
            $dto->reference,
        );
    }

    /** @return Collection<int, LedgerEntry> */
    public function reverse(
        TransactionType $originalType,
        int $originalTransactionId,
        int $reversalTransactionId,
        ?string $reference = null,
    ): Collection {
        return $this->reverseDto(new ReverseJournalDto(
            $originalType,
            $originalTransactionId,
            $reversalTransactionId,
            $reference,
        ));
    }

    /** @return Collection<int, LedgerEntry> */
    public function reverseDto(ReverseJournalDto $dto): Collection
    {
        $original = $this->entries->findByTransaction($dto->originalType, $dto->originalTransactionId);

        if ($original->isEmpty()) {
            return collect();
        }

        if ($this->isReversed($dto->originalType, $dto->originalTransactionId)) {
            return collect();
        }

        $lines = $original->map(fn (LedgerEntry $e) => [
            'ledger_account_type' => $e->ledger_account_type,
            'ledger_account_id' => $e->ledger_account_id,
            'debit' => bcadd((string) $e->credit, '0', 2),
            'credit' => bcadd((string) $e->debit, '0', 2),
        ])->all();

        $ref = $dto->reference ?? $this->reversalReference($dto->originalType, $dto->originalTransactionId);

        $entries = $this->postJournal(
            TransactionType::REVERSAL,
            $dto->reversalTransactionId,
            $lines,
            $ref,
        );

        event(new LedgerReversed($dto->originalType, $dto->originalTransactionId, $entries));

        return $entries;
    }

    public function isReversed(TransactionType $originalType, int $originalTransactionId): bool
    {
        return $this->entries->reversalExists(
            $this->reversalReference($originalType, $originalTransactionId)
        );
    }

    public function totalDebits(): string
    {
        return $this->normalize($this->entries->sumDebits());
    }

    public function totalCredits(): string
    {
        return $this->normalize($this->entries->sumCredits());
    }

    /** @deprecated Alias backward-compat — gunakan balanceForAccount(). */
    public function ledgerSumForAccount(int $accountId): string
    {
        return $this->balanceForAccount($accountId);
    }

    /** @deprecated Alias backward-compat — gunakan balanceForFund(). */
    public function ledgerSumForFund(int $fundId): string
    {
        return $this->balanceForFund($fundId);
    }

    /** @deprecated Gunakan postAmanahMovement() atau postJournal(). */
    public function post(array $legs, $actor = null): Collection
    {
        if ($legs === []) {
            return collect();
        }

        $first = $legs[0];
        $type = $first['type'] ?? TransactionType::ADJUSTMENT;
        $transactionType = $type instanceof TransactionType
            ? $type
            : TransactionType::tryFrom((string) $type) ?? TransactionType::ADJUSTMENT;

        $source = $first['source'] ?? null;
        $transactionId = $source?->getKey() ?? 0;
        $reference = $first['memo'] ?? null;

        $fundLines = array_map(fn (array $leg) => [
            'fund_id' => (int) $leg['fund_id'],
            'amount' => bcadd((string) $leg['amount'], '0', 2),
        ], $legs);

        $movement = bccomp((string) ($fundLines[0]['amount'] ?? '0'), '0', 2) >= 0
            ? LedgerMovement::IN
            : LedgerMovement::OUT;

        if ($movement === LedgerMovement::OUT) {
            $fundLines = array_map(fn (array $row) => [
                'fund_id' => $row['fund_id'],
                'amount' => ltrim($row['amount'], '-'),
            ], $fundLines);
        }

        return $this->postAmanahMovement(
            $transactionType,
            (int) $transactionId,
            (int) $first['account_id'],
            $fundLines,
            $movement,
            $reference
        );
    }

    /** @deprecated Gunakan reverse(). */
    public function reverseModel(Model $source, $actor = null, ?string $memo = null): Collection
    {
        $type = match ($source::class) {
            Receipt::class => TransactionType::RECEIPT,
            Disbursement::class => TransactionType::EXPENSE,
            BankFee::class => TransactionType::BANK_FEE,
            default => TransactionType::ADJUSTMENT,
        };

        return $this->reverse($type, (int) $source->getKey(), (int) $source->getKey(), $memo);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function lockLedgerAccounts(array $lines): void
    {
        $fundIds = [];
        $accountIds = [];

        foreach ($lines as $line) {
            if ($line['ledger_account_type'] === LedgerAccountType::FUND) {
                $fundIds[$line['ledger_account_id']] = true;
            } else {
                $accountIds[$line['ledger_account_id']] = true;
            }
        }

        ksort($fundIds);
        foreach (array_keys($fundIds) as $fundId) {
            Fund::query()->whereKey($fundId)->lockForUpdate()->first();
        }

        ksort($accountIds);
        foreach (array_keys($accountIds) as $accountId) {
            Account::query()->whereKey($accountId)->lockForUpdate()->first();
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function assertSufficientForOutflows(array $lines): void
    {
        $accountOut = [];
        $fundOut = [];

        foreach ($lines as $line) {
            if ($line['ledger_account_type'] === LedgerAccountType::ACCOUNT && bccomp($line['credit'], '0', 2) === 1) {
                $id = $line['ledger_account_id'];
                $accountOut[$id] = bcadd($accountOut[$id] ?? '0.00', $line['credit'], 2);
            }

            if ($line['ledger_account_type'] === LedgerAccountType::FUND && bccomp($line['debit'], '0', 2) === 1) {
                $id = $line['ledger_account_id'];
                $fundOut[$id] = bcadd($fundOut[$id] ?? '0.00', $line['debit'], 2);
            }
        }

        foreach ($accountOut as $accountId => $needed) {
            $balance = $this->balanceForAccount((int) $accountId);
            if (bccomp($balance, $needed, 2) < 0) {
                throw InsufficientBalanceException::account(
                    Account::find($accountId)?->name ?? "#{$accountId}",
                    $balance,
                    $needed
                );
            }
        }

        foreach ($fundOut as $fundId => $needed) {
            $balance = $this->balanceForFund((int) $fundId);
            if (bccomp($balance, $needed, 2) < 0) {
                throw InsufficientBalanceException::fund(
                    Fund::find($fundId)?->name ?? "#{$fundId}",
                    $balance,
                    $needed
                );
            }
        }
    }

    private function line(LedgerAccountType $type, int $id, string $debit, string $credit): array
    {
        return [
            'ledger_account_type' => $type,
            'ledger_account_id' => $id,
            'debit' => bcadd($debit, '0', 2),
            'credit' => bcadd($credit, '0', 2),
        ];
    }

    private function reversalReference(TransactionType $type, int $id): string
    {
        return "reversal:{$type->value}:{$id}";
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
