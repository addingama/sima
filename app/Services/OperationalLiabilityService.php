<?php

namespace App\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Exceptions\DomainException;
use App\Models\Disbursement;
use App\Models\OperationalLiability;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Register kewajiban operasional (utang belum dibayar).
 * Arus kas terjadi melalui Pengeluaran yang menyelesaikannya.
 */
class OperationalLiabilityService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AuditLogService $audit,
    ) {}

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            OperationalLiability::query()->with(['fund:id,code,name', 'program:id,code,name']),
            $query,
            searchColumns: ['liability_number', 'creditor', 'description'],
            sortable: ['liability_date', 'liability_number', 'amount', 'created_at'],
            defaultSort: 'liability_date',
            filterCallbacks: [
                'from' => fn ($q, $v) => $q->whereDate('liability_date', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('liability_date', '<=', $v),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): OperationalLiability
    {
        return DB::transaction(function () use ($data, $actor): OperationalLiability {
            $liability = OperationalLiability::create([
                ...$data,
                'liability_number' => $this->numbers->next('LIB'),
                'status' => 'outstanding',
                'amount_settled' => 0,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log($liability, 'created', null, $liability->toArray(), $actor);

            return $liability;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(OperationalLiability $liability, array $data, User $actor): OperationalLiability
    {
        if ($liability->status !== 'outstanding') {
            throw new DomainException('Hanya liabilitas berstatus outstanding yang dapat diubah.');
        }

        return DB::transaction(function () use ($liability, $data, $actor): OperationalLiability {
            $before = $liability->toArray();
            $liability->update($data);
            $this->audit->log($liability, 'updated', $before, $liability->fresh()->toArray(), $actor);

            return $liability->refresh();
        });
    }

    public function settle(OperationalLiability $liability, int $disbursementId, User $actor): OperationalLiability
    {
        if (in_array($liability->status, ['settled', 'void'], true)) {
            throw new DomainException('Liabilitas sudah selesai atau dibatalkan.');
        }

        return DB::transaction(function () use ($liability, $disbursementId, $actor): OperationalLiability {
            $disbursement = Disbursement::findOrFail($disbursementId);

            if ($disbursement->status->value !== 'approved') {
                throw new DomainException('Pengeluaran penyelesai harus berstatus approved (sudah ter-post).');
            }

            $before = $liability->toArray();
            $newSettled = bcadd((string) $liability->amount_settled, (string) $disbursement->amount, 2);
            $status = bccomp($newSettled, (string) $liability->amount, 2) >= 0
                ? 'settled'
                : 'partially_settled';

            $liability->update([
                'amount_settled' => $newSettled,
                'status' => $status,
                'settled_disbursement_id' => $disbursement->id,
                'settled_at' => $status === 'settled' ? now() : null,
            ]);

            $this->audit->log($liability, 'settled', $before, $liability->fresh()->toArray(), $actor);

            return $liability->refresh();
        });
    }

    public function void(OperationalLiability $liability, string $reason, User $actor): OperationalLiability
    {
        if ($liability->status === 'void') {
            throw new DomainException('Liabilitas sudah dibatalkan.');
        }

        return DB::transaction(function () use ($liability, $reason, $actor): OperationalLiability {
            $before = $liability->toArray();

            $liability->update([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => $actor->getKey(),
                'void_reason' => $reason,
            ]);

            $this->audit->log($liability, 'voided', $before, $liability->fresh()->toArray(), $actor, $reason);

            return $liability->refresh();
        });
    }

    public function findForShow(OperationalLiability $liability): OperationalLiability
    {
        return $liability->load([
            'fund:id,code,name',
            'program:id,code,name',
            'settledDisbursement',
            'attachments',
        ]);
    }
}
