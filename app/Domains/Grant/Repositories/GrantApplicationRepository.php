<?php

namespace App\Domains\Grant\Repositories;

use App\Enums\GrantApplicationStatus;
use App\Models\GrantApplication;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GrantApplicationRepository
{
    public function paginate(ListQueryDto $query, User $viewer): LengthAwarePaginator
    {
        return $this->applyListFilters(
            $this->visibleTo($viewer)->with([
                'assignedVerifier:id,name',
                'assignedHandover:id,name',
                'program:id,code,name',
                'createdBy:id,name',
                'handedOverBy:id,name',
            ]),
            $query,
            $viewer,
        )->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /** @return array<string, int|string> */
    public function summarize(ListQueryDto $query, User $viewer): array
    {
        $rows = $this->applyListFilters($this->visibleTo($viewer), $query, $viewer)
            ->reorder()
            ->selectRaw(
                'status,
                COUNT(*) as cnt,
                COALESCE(SUM(recommended_amount), 0) as recommended_sum,
                COALESCE(SUM(approved_amount), 0) as approved_sum,
                COALESCE(SUM(CASE
                    WHEN status = ? THEN 0
                    WHEN beneficiary_scope = ? THEN COALESCE(target_beneficiary_count, 0)
                    ELSE 1
                END), 0) as target_beneficiary_sum,
                COALESCE(SUM(CASE
                    WHEN status != ? THEN 0
                    WHEN beneficiary_scope = ? THEN COALESCE(actual_beneficiary_count, 0)
                    ELSE 1
                END), 0) as actual_beneficiary_sum',
                [
                    GrantApplicationStatus::REJECTED->value,
                    'collective',
                    GrantApplicationStatus::COMPLETED->value,
                    'collective',
                ]
            )
            ->groupBy('status')
            ->get();

        $jumlahKartu = 0;
        $antrian = 0;
        $usulan = '0.00';
        $disetujui = '0.00';
        $siapDiserahkan = '0.00';
        $sudahDiserahkan = '0.00';
        $ditolak = '0.00';
        $targetPenerimaManfaat = 0;
        $realisasiPenerimaManfaat = 0;

        $queueStatuses = [
            GrantApplicationStatus::DRAFT->value,
            GrantApplicationStatus::VERIFICATION->value,
            GrantApplicationStatus::PENDING_APPROVAL->value,
        ];

        foreach ($rows as $row) {
            $status = $row->status instanceof GrantApplicationStatus
                ? $row->status->value
                : (string) $row->status;
            $count = (int) $row->cnt;
            $jumlahKartu += $count;
            $recommended = bcadd((string) $row->recommended_sum, '0', 2);
            $approved = bcadd((string) $row->approved_sum, '0', 2);
            $usulan = bcadd($usulan, $recommended, 2);
            $targetPenerimaManfaat += (int) $row->target_beneficiary_sum;
            $realisasiPenerimaManfaat += (int) $row->actual_beneficiary_sum;

            if (in_array($status, $queueStatuses, true)) {
                $antrian += $count;
            }

            if ($status === GrantApplicationStatus::APPROVED->value) {
                $siapDiserahkan = bcadd($siapDiserahkan, $approved, 2);
                $disetujui = bcadd($disetujui, $approved, 2);
            }

            if ($status === GrantApplicationStatus::COMPLETED->value) {
                $sudahDiserahkan = bcadd($sudahDiserahkan, $approved, 2);
                $disetujui = bcadd($disetujui, $approved, 2);
            }

            if ($status === GrantApplicationStatus::REJECTED->value) {
                $ditolak = bcadd($ditolak, $recommended, 2);
            }
        }

        return [
            'jumlah_kartu' => $jumlahKartu,
            'antrian' => $antrian,
            'usulan' => $usulan,
            'disetujui' => $disetujui,
            'siap_diserahkan' => $siapDiserahkan,
            'sudah_diserahkan' => $sudahDiserahkan,
            'ditolak' => $ditolak,
            'target_penerima_manfaat' => $targetPenerimaManfaat,
            'realisasi_penerima_manfaat' => $realisasiPenerimaManfaat,
        ];
    }

    public function visibleTo(User $viewer): Builder
    {
        $query = GrantApplication::query();

        if ($this->seesAll($viewer)) {
            return $query;
        }

        return $query->where(function (Builder $w) use ($viewer): void {
            $w->where('created_by', $viewer->getKey())
                ->orWhere('assigned_verifier_id', $viewer->getKey())
                ->orWhere('assigned_handover_id', $viewer->getKey());
        });
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): GrantApplication
    {
        return GrantApplication::create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(GrantApplication $grant, array $data): GrantApplication
    {
        $grant->update($data);

        return $grant;
    }

    public function seesAll(User $viewer): bool
    {
        return $viewer->hasAnyRole(['admin', 'ketua', 'bendahara', 'auditor'])
            || $viewer->can('grant.assign');
    }

    private function applyListFilters(Builder $builder, ListQueryDto $query, User $viewer): Builder
    {
        return ListQueryApplier::apply(
            $builder,
            $query,
            searchColumns: ['application_number', 'recipient_name', 'organization_pic_name', 'recommender_name', 'reason'],
            sortable: ['created_at', 'application_number', 'recommended_amount', 'status'],
            defaultSort: 'created_at',
            filterCallbacks: $this->filterCallbacks($viewer),
        );
    }

    /** @return array<string, callable(Builder, mixed): void> */
    private function filterCallbacks(User $viewer): array
    {
        return [
            'mine' => function (Builder $q, mixed $v) use ($viewer): void {
                if (filter_var($v, FILTER_VALIDATE_BOOLEAN)) {
                    $q->where(function (Builder $inner) use ($viewer): void {
                        $inner->where('created_by', $viewer->getKey())
                            ->orWhere('assigned_verifier_id', $viewer->getKey())
                            ->orWhere('assigned_handover_id', $viewer->getKey());
                    });
                }
            },
            'status' => function (Builder $q, mixed $v): void {
                $items = is_array($v) ? $v : explode(',', (string) $v);
                $statuses = array_values(array_filter(array_map(
                    static fn (mixed $item): string => trim((string) $item),
                    $items,
                )));

                if ($statuses !== []) {
                    $q->whereIn('status', $statuses);
                }
            },
            'beneficiary_type' => function (Builder $q, mixed $v): void {
                $q->where('beneficiary_type', (string) $v);
            },
            'beneficiary_scope' => function (Builder $q, mixed $v): void {
                $q->where('beneficiary_scope', (string) $v);
            },
            'assigned_verifier_id' => function (Builder $q, mixed $v): void {
                $q->where('assigned_verifier_id', (int) $v);
            },
            'assigned_handover_id' => function (Builder $q, mixed $v): void {
                $q->where('assigned_handover_id', (int) $v);
            },
            'created_by' => function (Builder $q, mixed $v): void {
                $q->where('created_by', (int) $v);
            },
            'payment_method' => function (Builder $q, mixed $v): void {
                $q->where('payment_method', (string) $v);
            },
            'program_id' => function (Builder $q, mixed $v): void {
                $q->where('program_id', (int) $v);
            },
            'from' => function (Builder $q, mixed $v): void {
                $q->whereDate('created_at', '>=', (string) $v);
            },
            'to' => function (Builder $q, mixed $v): void {
                $q->whereDate('created_at', '<=', (string) $v);
            },
        ];
    }
}
