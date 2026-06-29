<?php

namespace App\Domains\Expense\Services;

use App\Domains\Expense\Events\BankFeeCreated;
use App\Domains\Expense\Events\BankFeeDeferred;
use App\Domains\Expense\Events\BankFeePosted;
use App\Domains\Expense\Events\BankFeeReversed;
use App\Domains\Expense\Validators\BankFeeValidator;
use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Ledger\Services\LedgerService;
use App\Enums\BankFeeStatus;
use App\Enums\LedgerMovement;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\BankFee;
use App\Models\Fund;
use App\Models\OperationalLiability;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Support\Facades\DB;

class BankFeeService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BalanceService $balances,
        private readonly DocumentNumberService $numbers,
        private readonly BankFeeValidator $validator,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): BankFee
    {
        return DB::transaction(function () use ($data, $actor): BankFee {
            if (empty($data['fund_id'])) {
                $data['fund_id'] = $this->operationalFund()->id;
            }

            $this->validator->assertFundAllowed((int) $data['fund_id']);

            $data['fee_number'] = $data['fee_number'] ?? $this->numbers->next('FEE');
            $data['status'] = BankFeeStatus::DRAFT->value;
            $data['created_by'] = $actor->getKey();

            $fee = BankFee::create($data);
            event(new BankFeeCreated($fee, $actor));

            return $fee;
        });
    }

    public function post(BankFee $fee, User $actor): BankFee
    {
        $this->validator->assertDraft($fee);
        $this->validator->assertFundAllowed($fee->fund_id);

        $amount = bcadd((string) $fee->amount, '0', 2);

        return DB::transaction(function () use ($fee, $amount, $actor): BankFee {
            if ($this->balances->hasSufficientFund($fee->fund_id, $amount)) {
                $this->balances->assertAccountSufficient($fee->account_id, $amount);

                $this->ledger->postAmanahMovement(
                    TransactionType::BANK_FEE,
                    $fee->id,
                    $fee->account_id,
                    [['fund_id' => $fee->fund_id, 'amount' => $amount]],
                    LedgerMovement::OUT,
                    'Biaya bank '.$fee->fee_number,
                );

                $fee->update([
                    'status' => BankFeeStatus::POSTED->value,
                    'posted_at' => now(),
                    'posted_by' => $actor->getKey(),
                ]);
                event(new BankFeePosted($fee->refresh(), $actor, $amount));

                return $fee->refresh();
            }

            $liability = OperationalLiability::create([
                'liability_number' => $this->numbers->next('LIB'),
                'liability_date' => $fee->fee_date->toDateString(),
                'creditor' => 'Bank',
                'description' => 'Biaya administrasi bank tertunda: '.$fee->fee_number,
                'fund_id' => $fee->fund_id,
                'amount' => $amount,
                'amount_settled' => 0,
                'status' => 'outstanding',
                'created_by' => $actor->getKey(),
            ]);

            $fee->update([
                'status' => BankFeeStatus::DEFERRED->value,
                'operational_liability_id' => $liability->id,
            ]);
            event(new BankFeeDeferred($fee->refresh(), $actor, $liability->id, $amount));

            return $fee->refresh();
        });
    }

    public function reverse(BankFee $fee, User $actor, string $reason): BankFee
    {
        $this->validator->assertPostedForReversal($fee);

        return DB::transaction(function () use ($fee, $actor, $reason): BankFee {
            $this->ledger->reverse(
                TransactionType::BANK_FEE,
                $fee->id,
                $fee->id,
                'Reversal biaya bank: '.$reason,
            );

            $fee->update([
                'status' => BankFeeStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);

            event(new BankFeeReversed($fee->refresh(), $actor, $reason));

            return $fee->refresh();
        });
    }

    private function operationalFund(): Fund
    {
        $fund = Fund::findBySystemKey(Fund::KEY_OPERATIONAL);

        if ($fund === null) {
            throw new DomainException('Dana Operasional (system) belum tersedia. Jalankan seeder.');
        }

        return $fund;
    }
}
