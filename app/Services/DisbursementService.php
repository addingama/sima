<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\DisbursementStatus;
use App\Enums\LedgerType;
use App\Exceptions\DomainException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Disbursement;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alur Pengeluaran: draft -> submitted -> verified -> approved (post ke ledger) -> [reversed]
 * Ditolak (rejected) dapat terjadi pada tahap submitted/verified.
 */
class DisbursementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Disbursement
    {
        $data['disbursement_number'] = $data['disbursement_number'] ?? $this->numbers->next('DSB');
        $data['status'] = DisbursementStatus::DRAFT->value;
        $data['created_by'] = $actor->getKey();

        return Disbursement::create($data);
    }

    public function submit(Disbursement $disbursement, User $actor): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::DRAFT]);
        $this->assertFundsAvailable($disbursement);

        $disbursement->update([
            'status' => DisbursementStatus::SUBMITTED->value,
            'submitted_at' => now(),
            'submitted_by' => $actor->getKey(),
        ]);
        $disbursement->recordApproval(ApprovalAction::SUBMITTED, $actor);

        return $disbursement->refresh();
    }

    public function verify(Disbursement $disbursement, User $actor, ?string $notes = null): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::SUBMITTED]);
        $this->assertFundsAvailable($disbursement);

        $disbursement->update([
            'status' => DisbursementStatus::VERIFIED->value,
            'verified_at' => now(),
            'verified_by' => $actor->getKey(),
        ]);
        $disbursement->recordApproval(ApprovalAction::VERIFIED, $actor, $notes);

        return $disbursement->refresh();
    }

    /** Persetujuan final (Ketua) — sekaligus memposting ke ledger. */
    public function approve(Disbursement $disbursement, User $actor, ?string $notes = null): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::VERIFIED]);

        return DB::transaction(function () use ($disbursement, $actor, $notes): Disbursement {
            $this->ledger->post([[
                'entry_date' => $disbursement->disbursement_date->toDateString(),
                'account_id' => $disbursement->account_id,
                'fund_id' => $disbursement->fund_id,
                'program_id' => $disbursement->program_id,
                'amount' => bcmul((string) $disbursement->amount, '-1', 2),
                'type' => LedgerType::DISBURSEMENT,
                'source' => $disbursement,
                'memo' => 'Pengeluaran '.$disbursement->disbursement_number,
            ]], $actor);

            $disbursement->update([
                'status' => DisbursementStatus::APPROVED->value,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
                'posted_at' => now(),
            ]);
            $disbursement->recordApproval(ApprovalAction::APPROVED, $actor, $notes);
            $disbursement->recordApproval(ApprovalAction::POSTED, $actor);

            return $disbursement->refresh();
        });
    }

    public function reject(Disbursement $disbursement, User $actor, string $reason): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::SUBMITTED, DisbursementStatus::VERIFIED]);

        $disbursement->update([
            'status' => DisbursementStatus::REJECTED->value,
            'rejected_at' => now(),
            'rejected_by' => $actor->getKey(),
            'rejection_reason' => $reason,
        ]);
        $disbursement->recordApproval(ApprovalAction::REJECTED, $actor, $reason);

        return $disbursement->refresh();
    }

    /** Reversal pengeluaran yang sudah ter-post (mengembalikan dana ke Dana Amanah & akun). */
    public function reverse(Disbursement $disbursement, User $actor, string $reason): Disbursement
    {
        $this->assertStatus($disbursement, [DisbursementStatus::APPROVED]);

        return DB::transaction(function () use ($disbursement, $actor, $reason): Disbursement {
            $this->ledger->reverse($disbursement, $actor, 'Reversal pengeluaran: '.$reason);

            $disbursement->update([
                'status' => DisbursementStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ]);
            $disbursement->recordApproval(ApprovalAction::REVERSED, $actor, $reason);

            return $disbursement->refresh();
        });
    }

    /** Validasi saldo Dana Amanah dan saldo akun mencukupi. */
    private function assertFundsAvailable(Disbursement $disbursement): void
    {
        $amount = bcadd((string) $disbursement->amount, '0', 2);

        $fundBalance = $this->ledger->balanceForFund($disbursement->fund_id);
        if (bccomp($fundBalance, $amount, 2) < 0) {
            $fund = Fund::find($disbursement->fund_id);
            throw InsufficientBalanceException::fund($fund?->name ?? '#', $fundBalance, $amount);
        }

        $accountBalance = $this->ledger->balanceForAccount($disbursement->account_id);
        if (bccomp($accountBalance, $amount, 2) < 0) {
            $account = Account::find($disbursement->account_id);
            throw InsufficientBalanceException::account($account?->name ?? '#', $accountBalance, $amount);
        }
    }

    /** @param array<int, DisbursementStatus> $allowed */
    private function assertStatus(Disbursement $disbursement, array $allowed): void
    {
        if (! in_array($disbursement->status, $allowed, true)) {
            $allowedLabels = implode(', ', array_map(fn (DisbursementStatus $s) => $s->value, $allowed));
            throw new DomainException(
                "Aksi tidak valid untuk status \"{$disbursement->status->value}\". Status yang diizinkan: {$allowedLabels}."
            );
        }
    }
}
