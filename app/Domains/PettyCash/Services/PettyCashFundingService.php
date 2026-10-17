<?php

namespace App\Domains\PettyCash\Services;

use App\Domains\Expense\Services\ExpenseService;
use App\Enums\PettyCashFloatStatus;
use App\Enums\PettyCashFundingKind;
use App\Enums\PettyCashTopupRequestStatus;
use App\Exceptions\DomainException;
use App\Models\Disbursement;
use App\Models\PettyCashFloat;
use App\Models\PettyCashFunding;
use App\Models\PettyCashTopupRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashFundingService
{
    public function __construct(
        private readonly ExpenseService $expenses,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $sources
     */
    public function createDisbursement(
        PettyCashFloat $float,
        array $data,
        array $sources,
        User $actor,
        PettyCashFundingKind $kind = PettyCashFundingKind::INITIAL,
        ?PettyCashTopupRequest $topupRequest = null,
    ): PettyCashFloat {
        $this->assertFloatOpen($float);
        $amount = bcadd((string) $data['amount'], '0', 2);
        if (bccomp($amount, '0', 2) !== 1) {
            throw ValidationException::withMessages(['amount' => ['Nominal harus lebih dari 0.']]);
        }

        if ($kind === PettyCashFundingKind::INITIAL && $float->fundings()->where('kind', PettyCashFundingKind::INITIAL)->exists()) {
            throw new DomainException('Float ini sudah punya pendanaan awal. Gunakan top-up untuk menambah.');
        }

        if ($kind === PettyCashFundingKind::TOPUP && $float->status === PettyCashFloatStatus::DRAFT) {
            throw new DomainException('Top-up hanya setelah float sudah dicairkan (reporting).');
        }

        $this->assertSingleFundSources($float, $sources);

        return DB::transaction(function () use ($float, $data, $sources, $actor, $kind, $topupRequest, $amount): PettyCashFloat {
            $expense = $this->expenses->create([
                'disbursement_date' => $data['disbursement_date'],
                'account_id' => $data['account_id'],
                'program_id' => $data['program_id'] ?? $float->program_id,
                'vendor_id' => $data['vendor_id'] ?? null,
                'amount' => $amount,
                'payee' => $data['payee'] ?? ($float->custodian?->name ?? 'PJ Kas Kecil'),
                'category' => $data['category'] ?? 'kas_kecil',
                'reference_number' => $data['reference_number'] ?? $float->float_number,
                'description' => $data['description'] ?? (
                    ($kind === PettyCashFundingKind::TOPUP ? 'Top-up ' : 'Cair ').$float->float_number
                ),
            ], $sources, $actor);

            $funding = $this->attachFunding($float, $expense, $kind, $actor, $data['notes'] ?? null);

            if ($topupRequest !== null) {
                $topupRequest->update([
                    'status' => PettyCashTopupRequestStatus::FUNDED,
                    'funding_id' => $funding->id,
                    'resolved_by' => $actor->id,
                    'resolved_at' => now(),
                ]);
            }

            return $float->refresh();
        });
    }

    public function syncAfterDisbursementApproved(Disbursement $disbursement): void
    {
        $funding = PettyCashFunding::query()
            ->where('disbursement_id', $disbursement->id)
            ->first();

        if ($funding === null) {
            return;
        }

        $float = $funding->float;
        if ($float === null) {
            return;
        }

        if ($float->status === PettyCashFloatStatus::DRAFT) {
            $float->update([
                'status' => PettyCashFloatStatus::REPORTING,
                'funded_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     */
    public function assertSingleFundSources(PettyCashFloat $float, array $sources): void
    {
        if (count($sources) !== 1) {
            throw ValidationException::withMessages([
                'sources' => ['MVP kas kecil: satu float hanya boleh satu Dana Amanah per pencairan.'],
            ]);
        }

        $fundId = (int) $sources[0]['fund_id'];
        $existingFundId = $this->lockedFundId($float);

        if ($existingFundId !== null && $existingFundId !== $fundId) {
            throw ValidationException::withMessages([
                'sources' => ["Dana Amanah harus sama dengan pencairan sebelumnya (fund_id={$existingFundId})."],
            ]);
        }
    }

    public function lockedFundId(PettyCashFloat $float): ?int
    {
        $float->loadMissing(['fundings.disbursement.fundSources']);

        foreach ($float->fundings as $funding) {
            $sources = $funding->disbursement?->fundSources;
            if ($sources === null || $sources->isEmpty()) {
                continue;
            }

            return (int) $sources->first()->fund_id;
        }

        return null;
    }

    private function attachFunding(
        PettyCashFloat $float,
        Disbursement $disbursement,
        PettyCashFundingKind $kind,
        User $actor,
        ?string $notes,
    ): PettyCashFunding {
        if (PettyCashFunding::query()->where('disbursement_id', $disbursement->id)->exists()) {
            throw new DomainException('Pengeluaran ini sudah tertaut ke float kas kecil.');
        }

        return PettyCashFunding::create([
            'petty_cash_float_id' => $float->id,
            'disbursement_id' => $disbursement->id,
            'kind' => $kind,
            'amount' => bcadd((string) $disbursement->amount, '0', 2),
            'requested_by' => $kind === PettyCashFundingKind::TOPUP ? $actor->id : null,
            'notes' => $notes,
        ]);
    }

    private function assertFloatOpen(PettyCashFloat $float): void
    {
        if (! in_array($float->status, [PettyCashFloatStatus::DRAFT, PettyCashFloatStatus::REPORTING], true)) {
            throw new DomainException('Float tidak dapat dicairkan pada status saat ini.');
        }
    }
}
