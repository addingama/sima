<?php

namespace App\Services;

use App\Enums\BankFeeStatus;
use App\Enums\LedgerMovement;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\BankFee;
use App\Models\Fund;
use App\Models\OperationalLiability;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Biaya Administrasi Bank.
 *
 * Aturan:
 *  - Default dibebankan ke Dana Operasional (system_key = operational).
 *  - Jika saldo Dana Operasional cukup -> posting ledger (kas -, dana operasional -).
 *  - Jika tidak cukup -> buat operational_liability (status fee = deferred), TANPA posting ledger.
 *  - JANGAN membebankan ke dana amanah khusus (restricted: zakat/qurban/wakaf) tanpa aturan eksplisit.
 */
class BankFeeService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly TrustFundBalanceService $balances,
        private readonly DocumentNumberService $numbers,
        private readonly AuditLogService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): BankFee
    {
        return DB::transaction(function () use ($data, $actor): BankFee {
            if (empty($data['fund_id'])) {
                $data['fund_id'] = $this->operationalFund()->id;
            }

            $this->assertFundAllowed((int) $data['fund_id']);

            $data['fee_number'] = $data['fee_number'] ?? $this->numbers->next('FEE');
            $data['status'] = BankFeeStatus::DRAFT->value;
            $data['created_by'] = $actor->getKey();

            $fee = BankFee::create($data);
            $this->audit->log($fee, 'created', null, $fee->toArray(), $actor);

            return $fee;
        });
    }

    /**
     * Posting biaya bank. Cukup -> ledger; tidak cukup -> operational_liability (deferred).
     */
    public function post(BankFee $fee, User $actor): BankFee
    {
        if ($fee->status !== BankFeeStatus::DRAFT) {
            throw new DomainException('Hanya biaya bank berstatus draft yang dapat diposting.');
        }

        $this->assertFundAllowed($fee->fund_id);

        $amount = bcadd((string) $fee->amount, '0', 2);

        return DB::transaction(function () use ($fee, $amount, $actor): BankFee {
            $fundEnough = $this->balances->hasSufficientFund($fee->fund_id, $amount);

            if ($fundEnough) {
                // Saldo akun juga harus cukup (uang fisik benar keluar dari bank).
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
                $this->audit->log($fee, 'posted', null, ['amount' => $amount], $actor);

                return $fee->refresh();
            }

            // Dana operasional tidak cukup -> catat sebagai liabilitas operasional (deferred).
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
            $this->audit->log($fee, 'deferred', null, [
                'liability_id' => $liability->id,
                'amount' => $amount,
            ], $actor);

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

    /** Cegah pembebanan biaya bank ke dana amanah khusus (restricted). */
    private function assertFundAllowed(int $fundId): void
    {
        $fund = Fund::findOrFail($fundId);

        if ($fund->type === 'restricted') {
            throw new DomainException(
                "Biaya bank tidak boleh dibebankan ke Dana Amanah khusus \"{$fund->name}\" (restricted). Gunakan Dana Operasional."
            );
        }
    }
}
