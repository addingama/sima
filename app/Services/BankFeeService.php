<?php

namespace App\Services;

use App\Enums\BankFeeStatus;
use App\Enums\LedgerType;
use App\Exceptions\DomainException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\BankFee;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BankFeeService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): BankFee
    {
        // Default dibebankan ke dana sistem "bank_admin" bila fund_id tidak ditentukan.
        if (empty($data['fund_id'])) {
            $data['fund_id'] = Fund::findBySystemKey(Fund::KEY_BANK_ADMIN)?->id;
        }

        $data['fee_number'] = $data['fee_number'] ?? $this->numbers->next('FEE');
        $data['status'] = BankFeeStatus::DRAFT->value;
        $data['created_by'] = $actor->getKey();

        return BankFee::create($data);
    }

    public function post(BankFee $fee, User $actor): BankFee
    {
        if ($fee->status !== BankFeeStatus::DRAFT) {
            throw new DomainException('Hanya biaya bank berstatus draft yang dapat diposting.');
        }

        $amount = bcadd((string) $fee->amount, '0', 2);

        $fundBalance = $this->ledger->balanceForFund($fee->fund_id);
        if (bccomp($fundBalance, $amount, 2) < 0) {
            $fund = Fund::find($fee->fund_id);
            throw InsufficientBalanceException::fund($fund?->name ?? '#', $fundBalance, $amount);
        }

        $accountBalance = $this->ledger->balanceForAccount($fee->account_id);
        if (bccomp($accountBalance, $amount, 2) < 0) {
            $account = Account::find($fee->account_id);
            throw InsufficientBalanceException::account($account?->name ?? '#', $accountBalance, $amount);
        }

        return DB::transaction(function () use ($fee, $amount, $actor): BankFee {
            $this->ledger->post([[
                'entry_date' => $fee->fee_date->toDateString(),
                'account_id' => $fee->account_id,
                'fund_id' => $fee->fund_id,
                'amount' => bcmul($amount, '-1', 2),
                'type' => LedgerType::BANK_FEE,
                'source' => $fee,
                'memo' => 'Biaya bank '.$fee->fee_number,
            ]], $actor);

            $fee->update([
                'status' => BankFeeStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor->getKey(),
            ]);

            return $fee->refresh();
        });
    }

    public function reverse(BankFee $fee, User $actor, string $reason): BankFee
    {
        if ($fee->status !== BankFeeStatus::POSTED) {
            throw new DomainException('Hanya biaya bank berstatus posted yang dapat dibatalkan.');
        }

        return DB::transaction(function () use ($fee, $actor, $reason): BankFee {
            $this->ledger->reverse($fee, $actor, 'Reversal biaya bank: '.$reason);

            $fee->update([
                'status' => BankFeeStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);

            return $fee->refresh();
        });
    }
}
