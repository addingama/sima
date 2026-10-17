<?php

namespace App\Domains\PettyCash\Services;

use App\Enums\PettyCashFloatStatus;
use App\Enums\PettyCashFundingKind;
use App\Enums\PettyCashTopupRequestStatus;
use App\Exceptions\DomainException;
use App\Models\PettyCashFloat;
use App\Models\PettyCashTopupRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashTopupService
{
    public function __construct(
        private readonly PettyCashFundingService $fundings,
    ) {}

    public function request(PettyCashFloat $float, string $amount, string $reason, User $actor): PettyCashTopupRequest
    {
        if ($float->status !== PettyCashFloatStatus::REPORTING) {
            throw new DomainException('Top-up hanya saat float berstatus reporting.');
        }

        if ((int) $float->custodian_user_id !== (int) $actor->id && ! $actor->hasAnyRole(['admin', 'bendahara', 'asisten_bendahara'])) {
            throw new DomainException('Hanya PJ float yang dapat meminta top-up.');
        }

        $normalized = bcadd($amount, '0', 2);
        if (bccomp($normalized, '0', 2) !== 1) {
            throw ValidationException::withMessages(['amount' => ['Nominal harus lebih dari 0.']]);
        }

        return PettyCashTopupRequest::create([
            'petty_cash_float_id' => $float->id,
            'amount' => $normalized,
            'reason' => $reason,
            'status' => PettyCashTopupRequestStatus::PENDING,
            'requested_by' => $actor->id,
        ]);
    }

    public function reject(PettyCashTopupRequest $request, User $actor, ?string $notes = null): PettyCashTopupRequest
    {
        if ($request->status !== PettyCashTopupRequestStatus::PENDING) {
            throw new DomainException('Permintaan top-up tidak dalam status pending.');
        }

        $request->update([
            'status' => PettyCashTopupRequestStatus::REJECTED,
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        return $request->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $sources
     */
    public function fund(PettyCashTopupRequest $request, array $data, array $sources, User $actor): PettyCashFloat
    {
        if ($request->status !== PettyCashTopupRequestStatus::PENDING) {
            throw new DomainException('Permintaan top-up tidak dalam status pending.');
        }

        $float = $request->float;
        $data['amount'] = bcadd((string) $request->amount, '0', 2);

        return DB::transaction(function () use ($float, $data, $sources, $actor, $request): PettyCashFloat {
            return $this->fundings->createDisbursement(
                $float,
                $data,
                $sources,
                $actor,
                PettyCashFundingKind::TOPUP,
                $request,
            );
        });
    }
}
